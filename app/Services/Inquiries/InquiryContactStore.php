<?php

namespace App\Services\Inquiries;

use App\Models\Inquiry;
use App\Models\InquiryContact;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Надёжное сохранение непроверенного контакта клиента обращения (AOA-04-I4, Gate A).
 *
 * КОНТРАКТ ВЕРСИИ (защита от ABA):
 *  - Контакт — необязательная строка 1:1 с САМОСТОЯТЕЛЬНЫМ обращением; её нет, пока контакт ни разу
 *    не вводили. Отсутствие строки означает версию 0.
 *  - Любое фактическое изменение (ввод, правка, очистка) увеличивает версию ровно на 1. Строка
 *    НИКОГДА не удаляется приложением: очистка обнуляет три поля и поднимает версию («надгробие»),
 *    поэтому счётчик не сбрасывается в 0 и форма, открытая до очистки, остаётся устаревшей и после
 *    повторного ввода контакта.
 *  - Сохранение принимает версию, которую сотрудник видел. Любое расхождение (в том числе после
 *    очистки и повторного создания) отклоняется, а не перезаписывает чужое изменение.
 *  - Все операции идут под блокировкой строки ОБРАЩЕНИЯ (так же, как InquiryDraftStore и
 *    InquiryAssignment), а не строки контакта: именно она сериализует «первое создание» двух
 *    вкладок, когда блокировать ещё нечего. Первичный ключ inquiry_id — вторая линия защиты.
 *  - Повтор тех же значений и очистка уже пустого контакта — безопасный no-op: ни записи, ни
 *    версии, ни события.
 *
 * Атомарность: изменение и событие аудита `inquiry.contact_updated` — в одной транзакции; сбой
 * аудита откатывает контакт. Событие несёт только идентификаторы, версии «до/после», ИМЕНА
 * изменённых полей и признак очистки — значения контакта в журнал, логи и исключения не попадают.
 *
 * Права перепроверяются под блокировкой по актуальной учётной записи: ответственный мог смениться
 * между показом формы и отправкой. Контакт не создаёт аккаунтов, не ищет и не связывает клиентов.
 */
class InquiryContactStore
{
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_SAVED = 'saved';
    public const RESULT_CLEARED = 'cleared';

    private const SHORT_NAMES = [
        'contact_name' => 'name',
        'contact_phone' => 'phone',
        'contact_messenger' => 'messenger',
    ];

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $input  см. InquiryContactData
     * @param  int  $expectedRevision  версия контакта, которую сотрудник видел на форме (0 — контакта не было)
     * @return self::RESULT_*
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws InquiryContactException
     */
    public function save(Inquiry $inquiry, User $actor, array $input, int $expectedRevision): string
    {
        return DB::transaction(function () use ($inquiry, $actor, $input, $expectedRevision): string {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

            $staff = User::query()->active()->find($actor->id);

            if ($staff === null) {
                throw new AuthorizationException('Эта учётная запись не может изменять контакт обращения.');
            }

            if (InquiryAuthority::provenance($locked) !== InquiryAuthority::PROVENANCE_INDEPENDENT) {
                // Контакт принадлежит только самостоятельному обращению; привязанное ведёт E5.
                throw new AuthorizationException('Контакт хранится только у самостоятельных обращений.');
            }

            Gate::forUser($staff)->authorize('updateContact', $locked);

            if (! $locked->isOpen()) {
                throw new InquiryContactException('Обращение уже оформлено или закрыто — контакт изменить нельзя.');
            }

            $current = InquiryContact::query()->lockForUpdate()->find($locked->id);
            $previousRevision = $current !== null ? (int) $current->revision : 0;

            if ($previousRevision !== $expectedRevision) {
                throw new InquiryContactException(
                    'Контакт уже изменён. Обновите страницу и повторите.',
                    InquiryContactException::FIELD_REVISION
                );
            }

            $values = InquiryContactData::normalize($input);

            $changed = [];
            foreach ($values as $field => $value) {
                if ($value !== ($current?->{$field})) {
                    $changed[] = self::SHORT_NAMES[$field];
                }
            }

            if ($changed === []) {
                return self::RESULT_UNCHANGED;
            }

            $cleared = array_filter($values, static fn (?string $v): bool => $v !== null) === [];
            $newRevision = $previousRevision + 1;

            try {
                if ($current === null) {
                    $row = new InquiryContact();
                    $row->forceFill(['inquiry_id' => $locked->id] + $values + ['revision' => $newRevision, 'updated_by' => $staff->id])->save();
                } else {
                    $current->forceFill($values + ['revision' => $newRevision, 'updated_by' => $staff->id])->save();
                }
            } catch (UniqueConstraintViolationException) {
                // Недостижимо при блокировке обращения; на случай обхода блокировки — как устаревшая форма.
                throw new InquiryContactException(
                    'Контакт уже изменён. Обновите страницу и повторите.',
                    InquiryContactException::FIELD_REVISION
                );
            }

            $this->audit->record(AuditEventType::InquiryContactUpdated, $staff, InquiryDraftStore::AUDIT_SUBJECT, (int) $locked->id, [
                'previous_revision' => $previousRevision,
                'new_revision' => $newRevision,
                'changed_fields' => implode(',', $changed),
                'cleared' => $cleared,
            ]);

            return $cleared ? self::RESULT_CLEARED : self::RESULT_SAVED;
        });
    }

    /**
     * Очистить все три поля. Строка остаётся «надгробием» с повышенной версией; очистка уже пустого
     * контакта — no-op.
     *
     * @return self::RESULT_*
     */
    public function clear(Inquiry $inquiry, User $actor, int $expectedRevision): string
    {
        return $this->save($inquiry, $actor, [
            'contact_name' => null,
            'contact_phone' => null,
            'contact_messenger' => null,
        ], $expectedRevision);
    }
}
