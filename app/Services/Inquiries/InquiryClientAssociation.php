<?php

namespace App\Services\Inquiries;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use App\Services\Accounts\StaffClientAccounts;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LogicException;

/**
 * Явная привязка аккаунта клиента к самостоятельному обращению (AOA-04-I4, Gate B/C).
 *
 * Аккаунт туриста связывается с обращением ТОЛЬКО осознанным действием сотрудника: ни имя, ни email,
 * ни телефон, ни контакт в мессенджере не служат основанием для автоматического сопоставления или
 * слияния. Контакт обращения (inquiry_contacts) и аккаунт (users) — разные сущности.
 *
 * Три входа в привязку: (1) linkVisible — менеджер выбирает ВИДИМОГО ему клиента (повторный клиент),
 * администратор — из всего справочника; (2) решение администратора по запросу проверки
 * (ClientVerificationService → applyLink); (3) createAndLink — только администратор, через общий
 * StaffClientAccounts. Исправление ошибочной привязки — только unlink администратором с причиной.
 *
 * Каждая операция — одна транзакция: блокировка строки обращения, повторная проверка прав по актуальной
 * учётной записи, допустимости обращения (самостоятельное, открытое), версии (expected_client_id) и
 * событие аудита. Сбой аудита откатывает изменение; отказ не оставляет события. Выбранного клиента
 * НИКОГДА не перезаписывают: сначала отвязка (с причиной), затем новая явная привязка.
 *
 * Порядок блокировок во всех операциях: Inquiry → (ClientVerificationRequest → инициатор) → клиент.
 * Это совпадает с InquiryAssignment (Inquiry → сотрудник), поэтому взаимных блокировок нет.
 *
 * Видимость клиента не даёт доступа к другим его заявкам, переписке и документам: они проверяются
 * собственными политиками и этой привязкой не расширяются.
 */
class InquiryClientAssociation
{
    public const VIA_SELECTED = 'selected';
    public const VIA_VERIFICATION = 'verification';
    public const VIA_CREATED = 'created';

    public const RESULT_LINKED = 'linked';
    public const RESULT_UNCHANGED = 'unchanged';

    public const REASON_MIN_LENGTH = 5;
    public const REASON_MAX_LENGTH = 200;

    /** Один и тот же текст для «нет такого», «чужого», «неактивного» и «не туриста»: наличие аккаунта не раскрывается. */
    public const CLIENT_UNAVAILABLE_MESSAGE = 'Этот клиент вам недоступен. Если клиент уже обращался в агентство, отправьте запрос на проверку клиента администратору.';

    public const EMAIL_CONFLICT_MESSAGE = 'Пользователь с таким email уже существует. Найдите его в справочнике клиентов и выберите явно.';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly StaffClientAccounts $accounts,
    ) {
    }

    /**
     * Записать привязку и событие аудита `inquiry.client_linked`. Вызывать ТОЛЬКО внутри транзакции
     * вызывающей операции, держа блокировку строки обращения. Выбранного ранее клиента не
     * перезаписывает: нарушение этого условия — ошибка программиста, а не пользовательский отказ.
     */
    public function applyLink(Inquiry $lockedInquiry, User $client, User $actor, string $via, ?int $verificationId = null): void
    {
        if ($lockedInquiry->client_user_id !== null) {
            throw new LogicException('An inquiry client is never overwritten; unlink it explicitly first.');
        }

        $lockedInquiry->forceFill([
            'client_user_id' => $client->id,
            'client_linked_by' => $actor->id,
            'client_linked_at' => now(),
        ])->save();

        $this->audit->record(AuditEventType::InquiryClientLinked, $actor, InquiryDraftStore::AUDIT_SUBJECT, (int) $lockedInquiry->id, [
            'client_id' => (int) $client->id,
            'via' => $via,
            'verification_id' => $verificationId,
        ]);
    }

    /**
     * Привязать клиента, ВИДИМОГО сотруднику (ClientVisibility): менеджер — только из своих клиентов,
     * администратор — из активных туристов. Чужой, несуществующий, неактивный и нетуристский id
     * неразличимы. Повтор той же привязки — безопасный no-op.
     *
     * @param  int|null  $expectedClientId  клиент обращения, которого сотрудник видел на форме (null — «не привязан»)
     * @return self::RESULT_*
     *
     * @throws AuthorizationException
     * @throws InquiryClientAssociationException
     */
    public function linkVisible(Inquiry $inquiry, User $actor, int $userId, ?int $expectedClientId): string
    {
        return DB::transaction(function () use ($inquiry, $actor, $userId, $expectedClientId): string {
            [$locked, $staff] = $this->lockForChange($inquiry, $actor, 'linkClient');

            $current = $locked->client_user_id !== null ? (int) $locked->client_user_id : null;

            if ($current !== null && $current === $userId) {
                return self::RESULT_UNCHANGED;
            }

            $this->assertExpected($current, $expectedClientId);

            if ($current !== null) {
                throw new InquiryClientAssociationException('К обращению уже привязан клиент. Изменить привязку может только администратор: он снимает её с указанием причины.');
            }

            $client = ClientVisibility::touristsVisibleTo($staff)->lockForUpdate()->find($userId);

            if ($client === null) {
                throw new InquiryClientAssociationException(self::CLIENT_UNAVAILABLE_MESSAGE, 'user_id');
            }

            $this->applyLink($locked, $client, $staff, self::VIA_SELECTED);

            return self::RESULT_LINKED;
        });
    }

    /**
     * Создать нового туриста по данным администратора и привязать к обращению. Безопасность аккаунта
     * обеспечивает общий StaffClientAccounts (случайный хешированный пароль, неподтверждённый email,
     * письма не отправляются); второй реализации создания аккаунтов нет. Существующий email аккаунт не
     * изменяет и не дублирует.
     *
     * @throws AuthorizationException
     * @throws InquiryClientAssociationException
     */
    public function createAndLink(Inquiry $inquiry, User $actor, string $name, ?string $email, ?string $phone, ?int $expectedClientId): User
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $email = $email !== null ? strtolower(trim($email)) : null;
        $email = $email === '' ? null : $email;
        $phone = $phone !== null ? trim($phone) : null;
        $phone = $phone === '' ? null : $phone;

        if ($name === '' || mb_strlen($name) > 255) {
            throw new InquiryClientAssociationException('Укажите имя клиента (не более 255 символов).', 'client_name');
        }

        try {
            return DB::transaction(function () use ($inquiry, $actor, $name, $email, $phone, $expectedClientId): User {
                [$locked, $staff] = $this->lockForChange($inquiry, $actor, 'administerClient');

                $current = $locked->client_user_id !== null ? (int) $locked->client_user_id : null;
                $this->assertExpected($current, $expectedClientId);

                if ($current !== null) {
                    throw new InquiryClientAssociationException('К обращению уже привязан клиент. Сначала снимите привязку с указанием причины.');
                }

                if ($email !== null && $this->accounts->emailExists($email)) {
                    throw new InquiryClientAssociationException(self::EMAIL_CONFLICT_MESSAGE, 'client_email');
                }

                // Без роли «tourist» createTourist бросает исключение — транзакция откатывается.
                $client = $this->accounts->createTourist($name, $email, $phone);

                $this->applyLink($locked, $client, $staff, self::VIA_CREATED);

                return $client;
            });
        } catch (UniqueConstraintViolationException) {
            throw new InquiryClientAssociationException(self::EMAIL_CONFLICT_MESSAGE, 'client_email');
        }
    }

    /**
     * Снять привязку клиента (исправление ошибки): только администратор, причина обязательна и
     * записывается в журнал. Следующая привязка — новое явное действие. Контакт обращения, черновик,
     * заявки и сам аккаунт клиента не затрагиваются.
     *
     * @throws AuthorizationException
     * @throws InquiryClientAssociationException
     */
    public function unlink(Inquiry $inquiry, User $actor, ?int $expectedClientId, string $reason): void
    {
        DB::transaction(function () use ($inquiry, $actor, $expectedClientId, $reason): void {
            [$locked, $staff] = $this->lockForChange($inquiry, $actor, 'administerClient');

            $current = $locked->client_user_id !== null ? (int) $locked->client_user_id : null;

            if ($current === null) {
                throw new InquiryClientAssociationException('К обращению не привязан клиент — снимать нечего. Обновите страницу.', InquiryClientAssociationException::FIELD_STALE);
            }

            $this->assertExpected($current, $expectedClientId);
            $reason = $this->cleanReason($reason);

            $locked->forceFill([
                'client_user_id' => null,
                'client_linked_by' => null,
                'client_linked_at' => null,
            ])->save();

            $this->audit->record(AuditEventType::InquiryClientUnlinked, $staff, InquiryDraftStore::AUDIT_SUBJECT, (int) $locked->id, [
                'previous_client_id' => $current,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Блокировка обращения и общие проверки: актуальная активная учётная запись, самостоятельное
     * открытое обращение, право на действие по свежим данным.
     *
     * @return array{0: Inquiry, 1: User}
     */
    private function lockForChange(Inquiry $inquiry, User $actor, string $ability): array
    {
        $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

        $staff = User::query()->active()->find($actor->id);

        if ($staff === null) {
            throw new AuthorizationException('Эта учётная запись не может менять клиента обращения.');
        }

        if (InquiryAuthority::provenance($locked) !== InquiryAuthority::PROVENANCE_INDEPENDENT) {
            // Клиента обращения, пришедшего из внешнего источника, ведёт рабочий процесс E5.
            throw new AuthorizationException('Клиент привязывается только к самостоятельным обращениям.');
        }

        Gate::forUser($staff)->authorize($ability, $locked);

        if (! $locked->isOpen()) {
            throw new InquiryClientAssociationException('Обращение уже оформлено или закрыто — клиента изменить нельзя.');
        }

        return [$locked, $staff];
    }

    private function assertExpected(?int $current, ?int $expected): void
    {
        if ($current !== $expected) {
            throw new InquiryClientAssociationException(
                'Клиент обращения уже изменён. Обновите страницу и повторите.',
                InquiryClientAssociationException::FIELD_STALE
            );
        }
    }

    /**
     * Причина обязательна, сжимается по пробелам и ограничена по длине; проверяется тем же валидатором
     * метаданных аудита, что и причина передачи обращения (email, телефоны, пути, токены отклоняются).
     */
    private function cleanReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        $length = mb_strlen($reason);

        if ($length < self::REASON_MIN_LENGTH || $length > self::REASON_MAX_LENGTH) {
            throw new InquiryClientAssociationException('Причина должна содержать от ' . self::REASON_MIN_LENGTH . ' до ' . self::REASON_MAX_LENGTH . ' символов.', 'reason');
        }

        try {
            AuditMetadata::validate(['reason' => $reason]);
        } catch (InvalidArgumentException) {
            throw new InquiryClientAssociationException('Причина не должна содержать email, телефоны, пути к файлам и длинные ключеподобные строки.', 'reason');
        }

        return $reason;
    }
}
