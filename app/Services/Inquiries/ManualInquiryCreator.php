<?php

namespace App\Services\Inquiries;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ручное создание обращения менеджером (AOA-04-I1, §A4: «создано менеджером — автоматически
 * назначено создавшему менеджеру, без одобрения администратора»).
 *
 * Доверенный актор — аутентифицированный менеджер, переданный вызывающим кодом. Создатель,
 * ответственный, происхождение, статус и отсутствие источника/заявки/клиента задаются ЗДЕСЬ и не
 * принимаются из запроса. Сервис не создаёт IncomingInquiry и Booking, не привязывает аккаунт
 * туриста, не отправляет писем и не обращается к провайдерам.
 *
 * Запись и событие аудита `inquiry.created` — в одной транзакции: сбой аудита откатывает и
 * обращение. Событие несёт только идентификаторы и код канала связи (без данных клиента и
 * черновика).
 *
 * Повторная отправка: токен показа формы (creation_token) уникален в паре с создателем. Повтор с
 * тем же токеном (двойной клик, повтор браузера, одновременные запросы) возвращает уже созданное
 * обращение без второй записи и без второго события аудита (`wasRecentlyCreated` = false).
 */
class ManualInquiryCreator
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function createForManager(User $actor, string $contactChannel, string $creationToken): Inquiry
    {
        Gate::forUser($actor)->authorize('create', Inquiry::class);

        if (! in_array($contactChannel, Inquiry::contactChannels(), true)) {
            throw ValidationException::withMessages([
                'contact_channel' => ['Выберите, как клиент связался с агентством: по телефону или в мессенджере.'],
            ]);
        }

        if (! Str::isUuid($creationToken)) {
            throw ValidationException::withMessages([
                'creation_token' => ['Форма устарела. Обновите страницу и повторите.'],
            ]);
        }

        $creationToken = strtolower($creationToken);

        try {
            return DB::transaction(function () use ($actor, $contactChannel, $creationToken): Inquiry {
                // Актуальная строка сотрудника: роль и активность могли измениться после входа.
                $staff = User::query()->lockForUpdate()->assignableToBookings()->find($actor->id);

                if ($staff === null) {
                    throw new AuthorizationException('Эта учётная запись не может вести обращения.');
                }

                Gate::forUser($staff)->authorize('create', Inquiry::class);

                $existing = $this->existing($staff, $creationToken);
                if ($existing !== null) {
                    return $existing;
                }

                $inquiry = new Inquiry();
                $inquiry->forceFill([
                    'origin' => Inquiry::ORIGIN_MANAGER_CREATED,
                    'contact_channel' => $contactChannel,
                    'creation_token' => $creationToken,
                    'status' => Inquiry::STATUS_IN_PROGRESS,
                    'incoming_inquiry_id' => null,
                    'created_by' => $staff->id,
                    'assigned_to' => $staff->id,
                    'assigned_at' => now(),
                ])->save();

                $this->audit->record(AuditEventType::InquiryCreated, $staff, InquiryDraftStore::AUDIT_SUBJECT, (int) $inquiry->id, [
                    'origin' => Inquiry::ORIGIN_MANAGER_CREATED,
                    'contact_channel' => $contactChannel,
                ]);

                return $inquiry;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Параллельный запрос с тем же токеном успел первым.
            $existing = $this->existing($actor, $creationToken);

            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }
    }

    private function existing(User $creator, string $creationToken): ?Inquiry
    {
        return Inquiry::query()
            ->where('created_by', $creator->id)
            ->where('creation_token', $creationToken)
            ->first();
    }
}
