<?php

namespace Tests\Feature;

use App\Events\BookingStatusChanged;
use App\Listeners\SendBookingStatusChangedNotification;
use App\Mail\BookingStatusChanged as BookingStatusChangedMail;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Проверяет контракт SendBookingStatusChangedNotification: письмо об
 * изменении статуса заявки ставится в очередь независимо для владельца
 * заявки и для назначенного менеджера, и только тому из них, кто (1)
 * присутствует, (2) не имеет технического email, (3) явно не отключил
 * email_notifications и (4) явно не отключил booking_updates. Настройки
 * по умолчанию (null, отсутствующие ключи) трактуются как «включено».
 *
 * Слушатель вызывается напрямую (без диспетчера событий и без HTTP-слоя),
 * чтобы не дублировать уже существующие тесты авторизации/переходов статуса
 * в BookingStatusTransitionTest.
 */
class BookingStatusEmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    // -----------------------------------------------------------------------
    // 1. Настройки по умолчанию / null — оба письма ставятся в очередь
    // -----------------------------------------------------------------------

    public function test_default_null_settings_queue_mail_to_owner_and_manager(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->assertNull($owner->notification_settings);
        $this->assertNull($manager->notification_settings);

        $this->dispatchListener($booking, Booking::STATUS_NEW);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->booking->id === $booking->id
                && $mail->oldStatus === Booking::STATUS_NEW
                && $mail->recipient->id === $owner->id
        );

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($manager->email)
                && $mail->booking->id === $booking->id
                && $mail->oldStatus === Booking::STATUS_NEW
                && $mail->recipient->id === $manager->id
        );

        Mail::assertQueuedCount(2);
    }

    // -----------------------------------------------------------------------
    // 2. Owner email_notifications=false — подавляет только письмо владельцу
    // -----------------------------------------------------------------------

    public function test_owner_email_notifications_false_suppresses_only_owner_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($manager->email)
                && $mail->recipient->id === $manager->id
        );

        Mail::assertNotQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $owner->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 3. Owner booking_updates=false — подавляет только письмо владельцу
    // -----------------------------------------------------------------------

    public function test_owner_booking_updates_false_suppresses_only_owner_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($manager->email)
                && $mail->recipient->id === $manager->id
        );

        Mail::assertNotQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $owner->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 4. Manager email_notifications=false — подавляет только письмо менеджеру
    // -----------------------------------------------------------------------

    public function test_manager_email_notifications_false_suppresses_only_manager_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->recipient->id === $owner->id
        );

        Mail::assertNotQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $manager->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 5. Manager booking_updates=false — подавляет только письмо менеджеру
    // -----------------------------------------------------------------------

    public function test_manager_booking_updates_false_suppresses_only_manager_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->recipient->id === $owner->id
        );

        Mail::assertNotQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $manager->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 6. Несвязанный new_messages=false не подавляет письмо о статусе заявки
    // -----------------------------------------------------------------------

    public function test_unrelated_new_messages_false_does_not_suppress_booking_status_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'new_messages' => false,
            ]),
        ])->save();

        $manager->forceFill([
            'notification_settings' => json_encode([
                'new_messages' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $owner->id
        );

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $manager->id
        );

        Mail::assertQueuedCount(2);
    }

    // -----------------------------------------------------------------------
    // 7. Текущий сгенерированный технический email — подавляет только этого получателя
    // -----------------------------------------------------------------------

    public function test_current_generated_technical_email_suppresses_only_that_recipient(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $owner->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->assertTrue($owner->fresh()->hasTechnicalEmail());

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($manager->email)
                && $mail->recipient->id === $manager->id
        );

        Mail::assertNotQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $owner->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 8. Устаревший (legacy) технический email — подавляет только этого получателя
    // -----------------------------------------------------------------------

    public function test_legacy_technical_email_suppresses_only_that_recipient(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill(['email' => 'temp_1736600000@avilona.ru'])->save();

        $this->assertTrue($manager->fresh()->hasTechnicalEmail());

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->recipient->id === $owner->id
        );

        Mail::assertNotQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool => $mail->recipient->id === $manager->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 9. Нет назначенного менеджера — письмо ставится в очередь только владельцу
    // -----------------------------------------------------------------------

    public function test_no_assigned_manager_queues_only_owner_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, null);

        $this->dispatchListener($booking, Booking::STATUS_NEW);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->recipient->id === $owner->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 10. Оба существующих получателя неприемлемы — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_both_ineligible_recipients_queue_no_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $manager->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking, Booking::STATUS_PROGRESS);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeBookingFor(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => $status,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => '2026-08-15',
                'nights'              => 7,
                'adults'              => 2,
                'children'            => 0,
            ])
        );
    }

    private function dispatchListener(Booking $booking, string $oldStatus): void
    {
        (new SendBookingStatusChangedNotification())->handle(
            new BookingStatusChanged($booking->fresh(), $oldStatus)
        );
    }
}
