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
 * назначено создавшему менеджеру, без одобрения администратора») или администратором (AOA-04-I3:
 * ответственного выбирает администратор).
 *
 * Доверенный актор — аутентифицированный сотрудник, переданный вызывающим кодом. Создатель,
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

        $creationToken = $this->cleanInput($contactChannel, $creationToken);

        return $this->run($actor, $creationToken, Inquiry::ORIGIN_MANAGER_CREATED, function () use ($actor, $contactChannel, $creationToken): Inquiry {
            // Актуальная строка сотрудника: роль и активность могли измениться после входа.
            $staff = User::query()->lockForUpdate()->assignableToBookings()->find($actor->id);

            if ($staff === null) {
                throw new AuthorizationException('Эта учётная запись не может вести обращения.');
            }

            Gate::forUser($staff)->authorize('create', Inquiry::class);

            return $this->existing($staff, $creationToken, Inquiry::ORIGIN_MANAGER_CREATED)
                ?? $this->persist($staff, $staff, Inquiry::ORIGIN_MANAGER_CREATED, $contactChannel, $creationToken, []);
        });
    }

    /**
     * Создание администратором (AOA-04-I3): создатель — сам администратор (его не подменяют), первый
     * ответственный — выбранный им допустимый сотрудник (User::assignableToBookings(): активный
     * менеджер или администратор). Допустимость сотрудника перепроверяется под транзакцией; повтор
     * с тем же токеном возвращает уже созданное обращение БЕЗ изменений (новый сотрудник/канал из
     * повторной отправки не применяются) и без нового события аудита.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function createForAdmin(User $actor, int $assigneeId, string $contactChannel, string $creationToken): Inquiry
    {
        Gate::forUser($actor)->authorize('createForStaff', Inquiry::class);

        $creationToken = $this->cleanInput($contactChannel, $creationToken);

        return $this->run($actor, $creationToken, Inquiry::ORIGIN_ADMIN_CREATED, function () use ($actor, $assigneeId, $contactChannel, $creationToken): Inquiry {
            $admin = User::query()->active()->lockForUpdate()->find($actor->id);

            if ($admin === null) {
                throw new AuthorizationException('Эта учётная запись не может создавать обращения.');
            }

            Gate::forUser($admin)->authorize('createForStaff', Inquiry::class);

            $existing = $this->existing($admin, $creationToken, Inquiry::ORIGIN_ADMIN_CREATED);
            if ($existing !== null) {
                return $existing;
            }

            $assignee = User::query()->assignableToBookings()->lockForUpdate()->find($assigneeId);

            if ($assignee === null) {
                throw ValidationException::withMessages([
                    'assignee_id' => ['Выбранного сотрудника нельзя назначить ответственным за обращение.'],
                ]);
            }

            return $this->persist($admin, $assignee, Inquiry::ORIGIN_ADMIN_CREATED, $contactChannel, $creationToken, [
                'initial_assignee_id' => (int) $assignee->id,
            ]);
        });
    }

    /** @return string нормализованный токен */
    private function cleanInput(string $contactChannel, string $creationToken): string
    {
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

        return strtolower($creationToken);
    }

    /** @param  callable(): Inquiry  $work */
    private function run(User $actor, string $creationToken, string $origin, callable $work): Inquiry
    {
        try {
            return DB::transaction($work);
        } catch (UniqueConstraintViolationException $e) {
            // Параллельный запрос с тем же токеном успел первым.
            $existing = $this->existing($actor, $creationToken, $origin);

            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }
    }

    /**
     * Запись и событие аудита (внутри внешней транзакции). Создатель, ответственный, происхождение,
     * статус и отсутствие источника/заявки/клиента задаются только здесь.
     *
     * @param  array<string, int>  $extraAudit
     */
    private function persist(User $creator, User $assignee, string $origin, string $contactChannel, string $creationToken, array $extraAudit): Inquiry
    {
        $inquiry = new Inquiry();
        $inquiry->forceFill([
            'origin' => $origin,
            'contact_channel' => $contactChannel,
            'creation_token' => $creationToken,
            'status' => Inquiry::STATUS_IN_PROGRESS,
            'incoming_inquiry_id' => null,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'assigned_at' => now(),
        ])->save();

        $this->audit->record(AuditEventType::InquiryCreated, $creator, InquiryDraftStore::AUDIT_SUBJECT, (int) $inquiry->id, [
            'origin' => $origin,
            'contact_channel' => $contactChannel,
        ] + $extraAudit);

        return $inquiry;
    }

    private function existing(User $creator, string $creationToken, string $origin): ?Inquiry
    {
        return Inquiry::query()
            ->where('created_by', $creator->id)
            ->where('origin', $origin)
            ->where('creation_token', $creationToken)
            ->first();
    }
}
