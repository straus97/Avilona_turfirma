<?php

namespace App\Services\Accounts;

use App\Models\ClientVerificationRequest;
use App\Models\IncomingInquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\IncomingInquiries\IncomingInquiryClientMatcher;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Проверка клиента менеджера администратором (AOA-01-I7, решение владельца по I3-F3: вариант C).
 *
 * Менеджер НЕ создаёт аккаунты туристов и не узнаёт, существует ли email: отправка
 * запроса вообще не обращается к таблице пользователей, поэтому её результат (и время
 * ответа) не зависят от существования аккаунта. Решение принимает только администратор:
 * привязать существующего туриста, одобрить создание нового или отклонить запрос.
 *
 * Каждое решение выполняется в одной транзакции под блокировкой строки запроса;
 * повторное решение отклоняется, событие аудита пишется внутри транзакции (сбой аудита
 * откатывает решение). Письма не отправляются: пароль не создаётся и не рассылается.
 */
class ClientVerificationService
{
    public const AUDIT_SUBJECT = 'client_verification';

    /** Единственный ответ менеджеру на отправку запроса — одинаковый для любого email. */
    public const NEUTRAL_MESSAGE = 'Запрос на проверку клиента передан администратору.';

    /** Одновременно открытых запросов одного менеджера (защита от флуда). */
    public const MAX_PENDING_PER_MANAGER = 25;

    /** Не более стольких отправок запросов одним менеджером в минуту (любой способ отправки). */
    public const MAX_SUBMISSIONS_PER_MINUTE = 10;

    public const NOTE_MIN_LENGTH = 5;
    public const NOTE_MAX_LENGTH = 300;

    private const CANDIDATE_LIMIT = 10;

    public function __construct(
        private readonly StaffClientAccounts $accounts,
        private readonly AuditRecorder $audit,
    ) {
    }

    // ------------------------------------------------------------------
    // Менеджер
    // ------------------------------------------------------------------

    /**
     * Принять запрос менеджера. Повтор того же запроса, пока он на проверке,
     * возвращает уже существующий (второй строки и второго события нет).
     */
    public function submit(User $manager, string $name, ?string $email, ?string $phone, ?IncomingInquiry $inquiry = null): ClientVerificationRequest
    {
        if (! $manager->hasRole(User::ROLE_MANAGER) || $manager->hasRole(User::ROLE_ADMIN) || ! User::query()->active()->whereKey($manager->id)->exists()) {
            throw new ClientVerificationException('Запрос на проверку клиента отправляет менеджер. Администратор создаёт клиентов напрямую.');
        }

        $limiterKey = 'client-verification:' . $manager->id;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_SUBMISSIONS_PER_MINUTE)) {
            throw new ClientVerificationException('Слишком много запросов за короткое время. Подождите минуту и повторите.', 'client_name');
        }

        RateLimiter::hit($limiterKey, 60);

        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $email = $email !== null ? strtolower(trim($email)) : null;
        $email = $email === '' ? null : $email;
        $phone = $phone !== null ? trim($phone) : null;
        $phone = $phone === '' ? null : $phone;

        $contextType = $inquiry !== null
            ? ClientVerificationRequest::CONTEXT_INCOMING_INQUIRY
            : ClientVerificationRequest::CONTEXT_BOOKING_FORM;

        $key = $this->pendingKey((int) $manager->id, $contextType, $inquiry?->id, $name, $email, $phone);

        try {
            return DB::transaction(function () use ($manager, $name, $email, $phone, $inquiry, $contextType, $key): ClientVerificationRequest {
                if ($inquiry !== null) {
                    $locked = IncomingInquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
                    Gate::forUser($manager)->authorize('process', $locked);

                    if (! $locked->isActionable() || $locked->workflow_state !== IncomingInquiry::WORKFLOW_IN_PROGRESS) {
                        throw new ClientVerificationException('Обращение сейчас нельзя обработать: проверьте его состояние.');
                    }
                }

                $existing = ClientVerificationRequest::query()->where('pending_key', $key)->first();

                if ($existing !== null) {
                    return $existing;
                }

                $pending = ClientVerificationRequest::query()
                    ->where('requested_by', $manager->id)
                    ->where('status', ClientVerificationRequest::STATUS_PENDING)
                    ->count();

                if ($pending >= self::MAX_PENDING_PER_MANAGER) {
                    throw new ClientVerificationException('Слишком много запросов на проверке. Дождитесь решения администратора по предыдущим.', 'client_name');
                }

                $request = new ClientVerificationRequest();
                $request->forceFill([
                    'requested_by' => $manager->id,
                    'context_type' => $contextType,
                    'incoming_inquiry_id' => $inquiry?->id,
                    'client_name' => $name,
                    'client_email' => $email,
                    'client_phone' => $phone,
                    'status' => ClientVerificationRequest::STATUS_PENDING,
                    'pending_key' => $key,
                ])->save();

                $this->audit->record(AuditEventType::ClientVerificationRequested, $manager, self::AUDIT_SUBJECT, (int) $request->id, [
                    'context' => $contextType,
                    'inquiry_id' => $inquiry?->id,
                    'has_email' => $email !== null,
                ]);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            // Параллельная отправка того же запроса: возвращаем победителя гонки.
            return ClientVerificationRequest::query()->where('pending_key', $key)->firstOrFail();
        }
    }

    // ------------------------------------------------------------------
    // Администратор
    // ------------------------------------------------------------------

    /** Привязать к менеджеру существующий аккаунт активного туриста. */
    public function linkExisting(ClientVerificationRequest $request, User $admin, int $userId): ClientVerificationRequest
    {
        return DB::transaction(function () use ($request, $admin, $userId): ClientVerificationRequest {
            $locked = $this->lockPending($request, $admin);
            $inquiry = $this->assertContextCurrent($locked);

            $client = IncomingInquiryClientMatcher::touristQuery()->lockForUpdate()->find($userId);

            if ($client === null) {
                throw new ClientVerificationException('Выбранный аккаунт нельзя привязать: он неактивен или не является туристом.', 'user_id');
            }

            $this->finish($locked, $admin, $client, ClientVerificationRequest::RESOLUTION_LINKED_EXISTING, $inquiry, AuditEventType::ClientVerificationLinked);

            return $locked->refresh();
        });
    }

    /** Одобрить создание нового туриста по данным запроса и привязать к менеджеру. */
    public function approveNew(ClientVerificationRequest $request, User $admin): ClientVerificationRequest
    {
        try {
            return DB::transaction(function () use ($request, $admin): ClientVerificationRequest {
                $locked = $this->lockPending($request, $admin);
                $inquiry = $this->assertContextCurrent($locked);

                if ($locked->client_email !== null && $this->accounts->emailExists($locked->client_email)) {
                    throw $this->emailBelongsToAccount();
                }

                $client = $this->accounts->createTourist($locked->client_name, $locked->client_email, $locked->client_phone);

                $this->finish($locked, $admin, $client, ClientVerificationRequest::RESOLUTION_CREATED_NEW, $inquiry, AuditEventType::ClientVerificationCreated);

                return $locked->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->emailBelongsToAccount();
        }
    }

    /** Отклонить запрос (допустимо и если контекст уже изменился). */
    public function reject(ClientVerificationRequest $request, User $admin, string $note): ClientVerificationRequest
    {
        $note = $this->cleanNote($note);

        return DB::transaction(function () use ($request, $admin, $note): ClientVerificationRequest {
            $locked = $this->lockPending($request, $admin);

            $updated = ClientVerificationRequest::query()
                ->whereKey($locked->id)
                ->where('status', ClientVerificationRequest::STATUS_PENDING)
                ->update([
                    'status' => ClientVerificationRequest::STATUS_REJECTED,
                    'pending_key' => null,
                    'resolved_by' => $admin->id,
                    'resolved_at' => now(),
                    'resolution' => ClientVerificationRequest::RESOLUTION_REJECTED,
                    'resolution_note' => $note,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new ClientVerificationException('Запрос уже обработан. Обновите страницу.');
            }

            $this->audit->record(AuditEventType::ClientVerificationRejected, $admin, self::AUDIT_SUBJECT, (int) $locked->id, [
                'manager_id' => (int) $locked->requested_by,
                'inquiry_id' => $locked->incoming_inquiry_id,
                'reason' => $note,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Существующие аккаунты, совпадающие с данными запроса по email (без учёта регистра)
     * или телефону. Только для администратора: показываются и сотрудники/неактивные
     * (с пометкой), чтобы решение было осознанным.
     *
     * @return Collection<int, array{user: User, reasons: list<string>, eligible: bool}>
     */
    public function candidatesFor(ClientVerificationRequest $request, User $admin): Collection
    {
        Gate::forUser($admin)->authorize('resolve', $request);

        $email = $request->client_email;
        $digits = (string) preg_replace('/\D+/', '', (string) $request->client_phone);
        $phone = strlen($digits) >= 10 ? substr($digits, -10) : null;

        if ($email === null && $phone === null) {
            return collect();
        }

        $users = User::query()
            ->with('roles:id,name')
            ->where(function (Builder $q) use ($email, $phone): void {
                if ($email !== null) {
                    $q->whereRaw('LOWER(email) = ?', [strtolower($email)]);
                }

                if ($phone !== null) {
                    $method = $email !== null ? 'orWhereRaw' : 'whereRaw';
                    $q->{$method}("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?", ['%' . $phone]);
                }
            })
            ->orderBy('name')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return $users->map(function (User $user) use ($email, $phone): array {
            $reasons = [];

            if ($email !== null && strtolower(trim((string) $user->email)) === strtolower($email)) {
                $reasons[] = 'совпадает email';
            }

            if ($phone !== null && str_ends_with((string) preg_replace('/\D+/', '', (string) $user->phone), $phone)) {
                $reasons[] = 'совпадает телефон';
            }

            return [
                'user' => $user,
                'reasons' => $reasons,
                'eligible' => (bool) $user->is_active && $user->hasRole(User::ROLE_TOURIST),
            ];
        });
    }

    // ------------------------------------------------------------------

    private function lockPending(ClientVerificationRequest $request, User $admin): ClientVerificationRequest
    {
        $locked = ClientVerificationRequest::query()->lockForUpdate()->findOrFail($request->id);

        Gate::forUser($admin)->authorize('resolve', $locked);

        if (! $locked->isPending()) {
            throw new ClientVerificationException('Запрос уже обработан. Повторное решение не применяется.');
        }

        return $locked;
    }

    /**
     * Контекст запроса перепроверяется на момент решения: инициатор — по-прежнему
     * активный менеджер, а обращение (если есть) ещё принадлежит ему и открыто.
     * Бывший ответственный не получает клиента лишь потому, что когда-то подал запрос.
     */
    private function assertContextCurrent(ClientVerificationRequest $locked): ?IncomingInquiry
    {
        $requester = User::query()
            ->active()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', User::ROLE_MANAGER))
            ->lockForUpdate()
            ->find($locked->requested_by);

        if ($requester === null || $requester->hasRole(User::ROLE_ADMIN)) {
            throw new ClientVerificationException('Инициатор запроса больше не является активным менеджером. Отклоните запрос.');
        }

        if ($locked->incoming_inquiry_id === null) {
            return null;
        }

        $inquiry = IncomingInquiry::query()->lockForUpdate()->find($locked->incoming_inquiry_id);

        if (
            $inquiry === null
            || ! $inquiry->isActionable()
            || $inquiry->workflow_state !== IncomingInquiry::WORKFLOW_IN_PROGRESS
            || (int) $inquiry->assigned_to !== (int) $locked->requested_by
        ) {
            throw new ClientVerificationException('Обращение уже переназначено, закрыто или оформлено — запрос потерял актуальность. Отклоните его.');
        }

        return $inquiry;
    }

    private function finish(
        ClientVerificationRequest $locked,
        User $admin,
        User $client,
        string $resolution,
        ?IncomingInquiry $inquiry,
        AuditEventType $type,
    ): void {
        $updated = ClientVerificationRequest::query()
            ->whereKey($locked->id)
            ->where('status', ClientVerificationRequest::STATUS_PENDING)
            ->update([
                'status' => ClientVerificationRequest::STATUS_APPROVED,
                'pending_key' => null,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'resolution' => $resolution,
                'resolved_user_id' => $client->id,
                'access_expires_at' => now()->addDays(ClientVerificationRequest::ACCESS_DAYS),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new ClientVerificationException('Запрос уже обработан. Обновите страницу.');
        }

        $linkedToInquiry = false;

        // Обращение без выбранного клиента получает его сразу; выбранного ранее не перезаписываем.
        if ($inquiry !== null && $inquiry->client_user_id === null) {
            $inquiry->forceFill([
                'client_user_id' => $client->id,
                'client_linked_by' => $admin->id,
                'client_linked_at' => now(),
            ])->save();
            $linkedToInquiry = true;
        }

        $this->audit->record($type, $admin, self::AUDIT_SUBJECT, (int) $locked->id, [
            'manager_id' => (int) $locked->requested_by,
            'client_id' => (int) $client->id,
            'inquiry_id' => $locked->incoming_inquiry_id,
            'linked_to_inquiry' => $linkedToInquiry,
        ]);
    }

    private function emailBelongsToAccount(): ClientVerificationException
    {
        return new ClientVerificationException(
            'Этот email уже принадлежит существующему аккаунту — новый не создаётся. Привяжите существующего туриста (если аккаунт — турист) или отклоните запрос.',
            'workflow'
        );
    }

    /** Комментарий к отклонению виден менеджеру: ограничен и безопасен для журнала аудита. */
    private function cleanNote(string $note): string
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        $length = mb_strlen($note);

        if ($length < self::NOTE_MIN_LENGTH || $length > self::NOTE_MAX_LENGTH) {
            throw new ClientVerificationException(
                'Укажите причину отклонения: от ' . self::NOTE_MIN_LENGTH . ' до ' . self::NOTE_MAX_LENGTH . ' символов.',
                'note'
            );
        }

        try {
            AuditMetadata::validate(['reason' => $note]);
        } catch (InvalidArgumentException) {
            throw new ClientVerificationException(
                'Причина не должна содержать email, телефоны, пути к файлам и длинные ключеподобные строки.',
                'note'
            );
        }

        return $note;
    }

    private function pendingKey(int $managerId, string $contextType, ?int $inquiryId, string $name, ?string $email, ?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);
        $identity = $email !== null ? 'e:' . $email : 'n:' . mb_strtolower($name) . '|p:' . $digits;

        return hash('sha256', implode('|', [$managerId, $contextType, (int) $inquiryId, $identity]));
    }
}
