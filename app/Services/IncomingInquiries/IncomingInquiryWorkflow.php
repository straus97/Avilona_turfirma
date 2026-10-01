<?php

namespace App\Services\IncomingInquiries;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\DestinationCity;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Бизнес-процесс обработки входящего обращения (E5-A3):
 * new → in_progress → converted | closed.
 *
 * Все операции явные и вызываются только сотрудником. Сервис НЕ вызывает
 * внешних провайдеров, не создаёт заявок у туроператора и не проводит оплату.
 * Состояние всегда перепроверяется внутри транзакции под блокировкой строки,
 * поэтому две вкладки / два сотрудника не могут обойти друг друга.
 *
 * Авторизация «кто» повторно проверяется здесь по актуальной строке (политика
 * IncomingInquiryPolicy), потому что ответственный мог смениться между показом
 * формы и отправкой.
 */
class IncomingInquiryWorkflow
{
    /**
     * Взять обращение в работу (атомарно: выигрывает ровно один сотрудник).
     *
     * @return bool true — взято сейчас; false — уже ваше (повторная отправка).
     */
    public function claim(IncomingInquiry $inquiry, User $actor): bool
    {
        $this->assertAssignable($actor->id, 'Вы не можете вести обращения: учётная запись неактивна или без роли сотрудника.');

        $updated = IncomingInquiry::query()
            ->whereKey($inquiry->id)
            ->actionable()
            ->where('workflow_state', IncomingInquiry::WORKFLOW_NEW)
            ->whereNull('assigned_to')
            ->update([
                'workflow_state' => IncomingInquiry::WORKFLOW_IN_PROGRESS,
                'assigned_to' => $actor->id,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            return true;
        }

        $current = IncomingInquiry::query()->findOrFail($inquiry->id);

        if (
            $current->workflow_state === IncomingInquiry::WORKFLOW_IN_PROGRESS
            && (int) $current->assigned_to === (int) $actor->id
        ) {
            return false;
        }

        throw new IncomingInquiryWorkflowException($this->claimRefusal($current));
    }

    /** Администратор передаёт обращение в работе другому сотруднику. */
    public function reassign(IncomingInquiry $inquiry, User $actor, int $newAssigneeId): bool
    {
        $this->assertAssignable($newAssigneeId, 'Выбранного сотрудника нельзя назначить ответственным.', 'assigned_to');

        return DB::transaction(function () use ($inquiry, $actor, $newAssigneeId): bool {
            $locked = $this->lock($inquiry);
            $this->authorize($actor, 'reassign', $locked);
            $this->assertInProgress($locked);

            if ((int) $locked->assigned_to === $newAssigneeId) {
                return false;
            }

            $locked->forceFill([
                'assigned_to' => $newAssigneeId,
                'claimed_at' => now(),
            ])->save();

            return true;
        });
    }

    /** Явно закрепить за обращением существующий аккаунт туриста. */
    public function selectClient(IncomingInquiry $inquiry, User $actor, int $userId): User
    {
        return DB::transaction(function () use ($inquiry, $actor, $userId): User {
            $locked = $this->lock($inquiry);
            $this->authorize($actor, 'process', $locked);
            $this->assertInProgress($locked);

            $client = IncomingInquiryClientMatcher::touristQuery()->lockForUpdate()->find($userId);

            if ($client === null) {
                throw new IncomingInquiryWorkflowException('Выберите клиента из списка активных туристов.', 'client_id');
            }

            $this->linkClient($locked, $client, $actor);

            return $client;
        });
    }

    /**
     * Создать нового туриста по данным обращения и закрепить его.
     *
     * Безопасность учётной записи:
     *  - пароль — случайные 64 символа, нигде не хранятся и не показываются
     *    (temp_password не используется), войти можно только через «Забыли пароль»;
     *  - email НЕ считается подтверждённым (email_verified_at = null);
     *  - существующий аккаунт с таким email не изменяется и не дублируется;
     *  - письма здесь не отправляются.
     */
    public function createClient(IncomingInquiry $inquiry, User $actor, string $name, ?string $email, ?string $phone): User
    {
        $email = $email !== null ? trim($email) : null;
        $email = $email === '' ? null : $email;

        try {
            return DB::transaction(function () use ($inquiry, $actor, $name, $email, $phone): User {
                $locked = $this->lock($inquiry);
                $this->authorize($actor, 'process', $locked);
                $this->assertInProgress($locked);

                if ($email !== null && User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
                    throw new IncomingInquiryWorkflowException(
                        'Пользователь с таким email уже существует. Найдите его в списке клиентов и выберите явно.',
                        'client_email'
                    );
                }

                $client = new User();
                $client->forceFill([
                    'name' => $name,
                    'email' => $email ?? $this->technicalEmail(),
                    'phone' => ($phone !== null && trim($phone) !== '') ? trim($phone) : null,
                    'password' => Hash::make(Str::random(64)),
                    'is_active' => true,
                    'password_change_required' => false,
                    'temp_password' => null,
                    'email_verified_at' => null,
                ])->save();

                // firstOrFail: без роли «tourist» вся транзакция откатывается.
                $client->assignRole(Role::TOURIST);

                $this->linkClient($locked, $client, $actor);

                return $client;
            });
        } catch (UniqueConstraintViolationException) {
            throw new IncomingInquiryWorkflowException(
                'Пользователь с таким email уже существует. Найдите его в списке клиентов и выберите явно.',
                'client_email'
            );
        }
    }

    /**
     * Отправить клиенту стандартную ссылку установки пароля (тот же механизм,
     * что «Забыли пароль»). Только для ещё не активированного аккаунта с
     * настоящим email: не даёт сотруднику способа слать ссылки активным пользователям.
     */
    public function sendPasswordSetup(IncomingInquiry $inquiry, User $actor): void
    {
        $locked = $this->lock($inquiry);
        $this->authorize($actor, 'process', $locked);
        $this->assertInProgress($locked);

        $client = $locked->client_user_id
            ? IncomingInquiryClientMatcher::touristQuery()->find($locked->client_user_id)
            : null;

        if ($client === null) {
            throw new IncomingInquiryWorkflowException('Сначала выберите или создайте клиента.', 'client_id');
        }

        if ($client->hasTechnicalEmail() || $client->email_verified_at !== null || $client->last_login_at !== null) {
            throw new IncomingInquiryWorkflowException(
                'Ссылку установки пароля можно отправить только клиенту, который ещё не входил в кабинет и имеет настоящий email.',
                'client_id'
            );
        }

        $status = Password::broker()->sendResetLink(['email' => $client->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new IncomingInquiryWorkflowException('Не удалось отправить ссылку. Попробуйте позже.', 'client_id');
        }
    }

    /**
     * Оформить ОДНУ нативную заявку Avilona по обращению.
     *
     * Цена: `total_price` — итоговая сумма всей заявки, подтверждённая менеджером.
     * Справочная цена Tourvisor (incoming_inquiries.price) нигде не используется
     * как значение заявки и ни на что не умножается.
     *
     * @param  array<string, mixed>  $data  уже провалидированные поля формы
     * @return array{booking: Booking, created: bool}  created=false — заявка уже была оформлена ранее
     */
    public function convert(IncomingInquiry $inquiry, User $actor, array $data): array
    {
        $result = DB::transaction(function () use ($inquiry, $actor, $data): array {
            $locked = $this->lock($inquiry);

            if ($locked->workflow_state === IncomingInquiry::WORKFLOW_CONVERTED && $locked->booking_id !== null) {
                return ['booking' => Booking::withTrashed()->findOrFail($locked->booking_id), 'created' => false];
            }

            $this->authorize($actor, 'process', $locked);
            $this->assertInProgress($locked);

            if ($locked->client_user_id === null) {
                throw new IncomingInquiryWorkflowException('Сначала выберите существующего клиента или создайте нового.', 'client_id');
            }

            $client = IncomingInquiryClientMatcher::touristQuery()->lockForUpdate()->find($locked->client_user_id);

            if ($client === null) {
                throw new IncomingInquiryWorkflowException('Выбранный клиент больше недоступен (неактивен или не турист). Выберите клиента заново.', 'client_id');
            }

            $assignee = User::query()->assignableToBookings()->lockForUpdate()->find($locked->assigned_to);

            if ($assignee === null) {
                throw new IncomingInquiryWorkflowException('Ответственный сотрудник больше не может вести заявки. Переназначьте обращение.', 'assigned_to');
            }

            // Явный белый список: владелец, ответственный, статус и tour_id
            // никогда не берутся из запроса.
            $booking = Booking::withoutEvents(fn (): Booking => Booking::create([
                'user_id' => $client->id,
                'manager_id' => $assignee->id,
                'tour_id' => null,
                'status' => Booking::STATUS_PROGRESS,
                'departure_city' => $data['departure_city'],
                'destination_country' => $data['destination_country'],
                'destination_city' => $data['destination_city'] ?? null,
                'start_date' => $data['start_date'],
                'start_date_end' => $data['start_date_end'] ?? null,
                'nights' => $data['nights'],
                'nights_max' => $data['nights_max'] ?? null,
                'adults' => $data['adults'],
                'children' => $data['children'] ?? 0,
                'children_ages' => $data['children_ages'] ?? null,
                'total_price' => $data['total_price'],
                'notes' => $data['notes'] ?? null,
                'manager_notes' => $data['manager_notes'] ?? null,
            ]));

            // Условный UPDATE — второй рубеж: даже при сбое блокировки строка
            // не перейдёт в converted дважды (плюс UNIQUE на booking_id).
            $marked = IncomingInquiry::query()
                ->whereKey($locked->id)
                ->where('workflow_state', IncomingInquiry::WORKFLOW_IN_PROGRESS)
                ->whereNull('booking_id')
                ->update([
                    'workflow_state' => IncomingInquiry::WORKFLOW_CONVERTED,
                    'booking_id' => $booking->id,
                    'converted_at' => now(),
                    'converted_by' => $actor->id,
                    'updated_at' => now(),
                ]);

            if ($marked !== 1) {
                throw new IncomingInquiryWorkflowException('Обращение уже обработано другим сотрудником. Обновите страницу.');
            }

            if (! empty($data['destination_country']) && ! empty($data['destination_city'])) {
                DestinationCity::addCityIfNotExists($data['destination_country'], $data['destination_city']);
            }

            return ['booking' => $booking, 'created' => true];
        });

        if ($result['created']) {
            // Заявка уже зафиксирована: сбой уведомления не откатывает её (как в BookingController::store).
            try {
                event(new BookingCreated($result['booking']));
            } catch (\Throwable $e) {
                Log::error('BookingCreated dispatch failed', [
                    'booking_id' => $result['booking']->id,
                    'exception' => get_class($e),
                ]);
            }
        }

        return $result;
    }

    /**
     * Закрыть обращение без бронирования (запись и снимок провайдера сохраняются).
     *
     * @return bool true — закрыто сейчас; false — уже было закрыто.
     */
    public function close(IncomingInquiry $inquiry, User $actor, ?string $reason): bool
    {
        return DB::transaction(function () use ($inquiry, $actor, $reason): bool {
            $locked = $this->lock($inquiry);

            if ($locked->workflow_state === IncomingInquiry::WORKFLOW_CLOSED) {
                return false;
            }

            $this->authorize($actor, 'close', $locked);

            if ($locked->workflow_state === IncomingInquiry::WORKFLOW_CONVERTED) {
                throw new IncomingInquiryWorkflowException('Обращение уже оформлено как заявка — закрыть его без бронирования нельзя.');
            }

            if (! $locked->isActionable()) {
                throw new IncomingInquiryWorkflowException('Это обращение пока нельзя обработать: данные не загружены.');
            }

            $reason = $reason !== null ? trim($reason) : null;

            $locked->forceFill([
                'workflow_state' => IncomingInquiry::WORKFLOW_CLOSED,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'close_reason' => $reason === '' ? null : $reason,
            ])->save();

            return true;
        });
    }

    // ------------------------------------------------------------------

    private function lock(IncomingInquiry $inquiry): IncomingInquiry
    {
        return IncomingInquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
    }

    private function authorize(User $actor, string $ability, IncomingInquiry $locked): void
    {
        Gate::forUser($actor)->authorize($ability, $locked);
    }

    private function assertInProgress(IncomingInquiry $locked): void
    {
        if (! $locked->isActionable()) {
            throw new IncomingInquiryWorkflowException('Это обращение пока нельзя обработать: данные не загружены.');
        }

        if ($locked->workflow_state !== IncomingInquiry::WORKFLOW_IN_PROGRESS) {
            throw new IncomingInquiryWorkflowException(match ($locked->workflow_state) {
                IncomingInquiry::WORKFLOW_NEW => 'Сначала возьмите обращение в работу.',
                IncomingInquiry::WORKFLOW_CONVERTED => 'Обращение уже оформлено как заявка.',
                IncomingInquiry::WORKFLOW_CLOSED => 'Обращение уже закрыто.',
                default => 'Обращение в этом состоянии обработать нельзя.',
            });
        }
    }

    /** Единый контракт назначаемого сотрудника — User::assignableToBookings(). */
    private function assertAssignable(int $userId, string $message, string $field = 'workflow'): void
    {
        if (! User::query()->assignableToBookings()->whereKey($userId)->exists()) {
            throw new IncomingInquiryWorkflowException($message, $field);
        }
    }

    private function linkClient(IncomingInquiry $locked, User $client, User $actor): void
    {
        $locked->forceFill([
            'client_user_id' => $client->id,
            'client_linked_by' => $actor->id,
            'client_linked_at' => now(),
        ])->save();
    }

    private function claimRefusal(IncomingInquiry $current): string
    {
        if (! $current->isActionable()) {
            return 'Это обращение пока нельзя взять в работу: данные не загружены.';
        }

        return match ($current->workflow_state) {
            IncomingInquiry::WORKFLOW_IN_PROGRESS => 'Обращение уже взято в работу другим сотрудником.',
            IncomingInquiry::WORKFLOW_CONVERTED => 'Обращение уже оформлено как заявка.',
            IncomingInquiry::WORKFLOW_CLOSED => 'Обращение уже закрыто.',
            default => 'Не удалось взять обращение в работу. Обновите страницу.',
        };
    }

    /** Технический адрес для клиента без email: недоставляемый домен + UUID (контракт User::hasTechnicalEmail()). */
    private function technicalEmail(): string
    {
        do {
            $email = 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN;
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
