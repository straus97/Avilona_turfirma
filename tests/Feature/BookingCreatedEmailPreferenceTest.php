<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Listeners\SendBookingCreatedNotification;
use App\Mail\AdminBookingCreated;
use App\Mail\BookingCreated as BookingCreatedMail;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Проверяет контракт SendBookingCreatedNotification: письмо о создании
 * заявки ставится в очередь независимо для владельца заявки (BookingCreated)
 * и для каждого администратора (AdminBookingCreated), и только тому из них,
 * кто (1) присутствует, (2) не имеет технического email, (3) явно не
 * отключил email_notifications и (4) явно не отключил booking_updates.
 * Настройки по умолчанию (null, отсутствующие ключи) трактуются как
 * «включено».
 *
 * Слушатель вызывается напрямую (без диспетчера событий и без HTTP-слоя),
 * чтобы не дублировать уже существующие тесты авторизации/владения/атомарности
 * в BookingCreationOwnershipAndAtomicityTest.
 */
class BookingCreatedEmailPreferenceTest extends TestCase
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

    public function test_default_null_settings_queue_mail_to_owner_and_administrator(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->assertNull($owner->notification_settings);
        $this->assertNull($admin->notification_settings);

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->booking->id === $booking->id
        );

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool =>
                $mail->hasTo($admin->email)
                && $mail->booking->id === $booking->id
        );

        Mail::assertQueuedCount(2);
    }

    // -----------------------------------------------------------------------
    // 2. Owner email_notifications=false — подавляет только письмо владельцу
    // -----------------------------------------------------------------------

    public function test_owner_email_notifications_false_suppresses_only_owner_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking);

        Mail::assertNotQueued(BookingCreatedMail::class);

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($admin->email)
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 3. Owner booking_updates=false — подавляет только письмо владельцу
    // -----------------------------------------------------------------------

    public function test_owner_booking_updates_false_suppresses_only_owner_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking);

        Mail::assertNotQueued(BookingCreatedMail::class);

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($admin->email)
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 4. Текущий сгенерированный технический email владельца — подавляет
    //    только письмо владельцу
    // -----------------------------------------------------------------------

    public function test_current_generated_technical_owner_email_suppresses_only_owner_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $owner->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->assertTrue($owner->fresh()->hasTechnicalEmail());

        $this->dispatchListener($booking);

        Mail::assertNotQueued(BookingCreatedMail::class);

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($admin->email)
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 5. Устаревший (legacy) технический email владельца — подавляет только
    //    письмо владельцу
    // -----------------------------------------------------------------------

    public function test_legacy_technical_owner_email_suppresses_only_owner_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $owner->forceFill(['email' => 'temp_1736600000@avilona.ru'])->save();

        $this->assertTrue($owner->fresh()->hasTechnicalEmail());

        $this->dispatchListener($booking);

        Mail::assertNotQueued(BookingCreatedMail::class);

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($admin->email)
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 6. Administrator email_notifications=false — подавляет только письмо
    //    этому администратору
    // -----------------------------------------------------------------------

    public function test_administrator_email_notifications_false_suppresses_only_that_administrator_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $admin->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        Mail::assertNotQueued(AdminBookingCreated::class);

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 7. Administrator booking_updates=false — подавляет только письмо этому
    //    администратору
    // -----------------------------------------------------------------------

    public function test_administrator_booking_updates_false_suppresses_only_that_administrator_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $admin->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        Mail::assertNotQueued(AdminBookingCreated::class);

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 8. Технический email администратора (текущий и legacy) — не получает
    //    письма, владелец получает своё письмо
    // -----------------------------------------------------------------------

    public function test_administrator_with_current_generated_technical_email_receives_no_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $admin->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->assertTrue($admin->fresh()->hasTechnicalEmail());

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        Mail::assertNotQueued(AdminBookingCreated::class);

        Mail::assertQueuedCount(1);
    }

    public function test_administrator_with_legacy_technical_email_receives_no_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $admin->forceFill(['email' => 'temp_1736600000@avilona.ru'])->save();

        $this->assertTrue($admin->fresh()->hasTechnicalEmail());

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        Mail::assertNotQueued(AdminBookingCreated::class);

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 9. Несвязанный new_messages=false не подавляет ни один из двух типов
    //    писем о создании заявки
    // -----------------------------------------------------------------------

    public function test_unrelated_new_messages_false_does_not_suppress_either_booking_created_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'new_messages' => false,
            ]),
        ])->save();

        $admin->forceFill([
            'notification_settings' => json_encode([
                'new_messages' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($admin->email)
        );

        Mail::assertQueuedCount(2);
    }

    // -----------------------------------------------------------------------
    // 10. Несколько администраторов со смешанной приемлемостью
    // -----------------------------------------------------------------------

    public function test_multiple_administrators_with_mixed_eligibility_each_receive_independent_delivery(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $eligibleAdmin1 = $this->makeUser(Role::ADMIN);
        $eligibleAdmin2 = $this->makeUser(Role::ADMIN);

        $emailDisabledAdmin = $this->makeUser(Role::ADMIN);
        $emailDisabledAdmin->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $bookingUpdatesDisabledAdmin = $this->makeUser(Role::ADMIN);
        $bookingUpdatesDisabledAdmin->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $technicalAdmin = $this->makeUser(Role::ADMIN);
        $technicalAdmin->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->dispatchListener($booking);

        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($eligibleAdmin1->email)
        );
        Mail::assertQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($eligibleAdmin2->email)
        );

        Mail::assertNotQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($emailDisabledAdmin->email)
        );
        Mail::assertNotQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($bookingUpdatesDisabledAdmin->email)
        );
        Mail::assertNotQueued(
            AdminBookingCreated::class,
            fn (AdminBookingCreated $mail): bool => $mail->hasTo($technicalAdmin->email)
        );

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        // 2 eligible admins + 1 owner mail.
        Mail::assertQueuedCount(3);
        Mail::assertQueued(AdminBookingCreated::class, 2);
        Mail::assertQueued(BookingCreatedMail::class, 1);
    }

    // -----------------------------------------------------------------------
    // 11. Нет пользователей-администраторов — только письмо владельцу
    // -----------------------------------------------------------------------

    public function test_no_administrator_users_queues_only_owner_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->dispatchListener($booking);

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo($owner->email)
        );

        Mail::assertNotQueued(AdminBookingCreated::class);
        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 12. Владелец и все администраторы неприемлемы — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_ineligible_owner_and_all_ineligible_administrators_queue_no_mail(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $owner->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $admin1 = $this->makeUser(Role::ADMIN);
        $admin1->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $admin2 = $this->makeUser(Role::ADMIN);
        $admin2->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->dispatchListener($booking);

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
        string $status = Booking::STATUS_NEW
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => null,
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

    private function dispatchListener(Booking $booking): void
    {
        (new SendBookingCreatedNotification())->handle(
            new BookingCreated($booking->fresh())
        );
    }
}
