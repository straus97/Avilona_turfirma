<?php

namespace Tests\Feature;

use App\Mail\TripReminder;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Проверяет контракт SendTripReminders: письмо-напоминание о поездке
 * ставится в очередь только владельцу подтверждённой заявки, стартующей
 * ровно через 1, 3, 7 или 14 дней, и только если владелец (1) существует,
 * (2) не имеет технического email, (3) явно не отключил email_notifications
 * и (4) явно не отключил trip_reminders. Настройки по умолчанию (null,
 * отсутствующие ключи) трактуются как «включено». Назначенный менеджер и
 * администраторы получателями письма не являются ни при каких условиях.
 */
class TripReminderEmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // 1. Настройки по умолчанию / null — письмо ставится в очередь
    // -----------------------------------------------------------------------

    public function test_default_null_settings_queue_mail_for_seven_day_booking(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $this->assertNull($owner->notification_settings);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->booking->id === $booking->id
                && $mail->daysUntilTrip === 7
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 2. Owner email_notifications=false — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_owner_email_notifications_false_queues_no_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
            ]),
        ])->save();

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 3. Owner trip_reminders=false — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_owner_trip_reminders_false_queues_no_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill([
            'notification_settings' => json_encode([
                'trip_reminders' => false,
            ]),
        ])->save();

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 4. Несвязанный booking_updates=false не подавляет письмо
    // -----------------------------------------------------------------------

    public function test_unrelated_booking_updates_false_does_not_suppress_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $this->artisan('bookings:send-trip-reminders')->assertExitCode(0);

        Mail::assertQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->booking->id === $booking->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 5. Несвязанный new_messages=false не подавляет письмо
    // -----------------------------------------------------------------------

    public function test_unrelated_new_messages_false_does_not_suppress_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill([
            'notification_settings' => json_encode([
                'new_messages' => false,
            ]),
        ])->save();

        $this->artisan('bookings:send-trip-reminders')->assertExitCode(0);

        Mail::assertQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->booking->id === $booking->id
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 6. Текущий сгенерированный технический email подавляет письмо
    // -----------------------------------------------------------------------

    public function test_current_generated_technical_email_suppresses_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->assertTrue($owner->fresh()->hasTechnicalEmail());

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 7. Устаревший (legacy) технический email подавляет письмо
    // -----------------------------------------------------------------------

    public function test_legacy_technical_email_suppresses_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill(['email' => 'temp_1736600000@avilona.ru'])->save();

        $this->assertTrue($owner->fresh()->hasTechnicalEmail());

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 8. Все четыре окна напоминаний (1, 3, 7, 14 дней) сохранены
    // -----------------------------------------------------------------------

    public function test_all_four_reminder_windows_queue_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $reminderDays = [1, 3, 7, 14];
        $owners = [];
        $bookings = [];

        foreach ($reminderDays as $days) {
            $owner = $this->makeUser(Role::TOURIST);
            $booking = $this->makeBookingFor($owner, Carbon::today()->addDays($days));

            $owners[$days] = $owner;
            $bookings[$days] = $booking;
        }

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 4')
            ->assertExitCode(0);

        foreach ($reminderDays as $days) {
            Mail::assertQueued(
                TripReminder::class,
                fn (TripReminder $mail): bool =>
                    $mail->hasTo($owners[$days]->email)
                    && $mail->booking->id === $bookings[$days]->id
                    && $mail->daysUntilTrip === $days
            );
        }

        Mail::assertQueuedCount(4);
    }

    // -----------------------------------------------------------------------
    // 9. Смешанная приемлемость получателей
    // -----------------------------------------------------------------------

    public function test_mixed_recipient_eligibility_queues_only_eligible_owners(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $eligibleOwner   = $this->makeUser(Role::TOURIST);
        $eligibleBooking = $this->makeBookingFor($eligibleOwner, Carbon::today()->addDays(3));

        $notificationsOffOwner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($notificationsOffOwner, Carbon::today()->addDays(7));
        $notificationsOffOwner->forceFill([
            'notification_settings' => json_encode(['email_notifications' => false]),
        ])->save();

        $tripRemindersOffOwner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($tripRemindersOffOwner, Carbon::today()->addDays(7));
        $tripRemindersOffOwner->forceFill([
            'notification_settings' => json_encode(['trip_reminders' => false]),
        ])->save();

        $technicalOwner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($technicalOwner, Carbon::today()->addDays(14));
        $technicalOwner->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $legacyTechnicalOwner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($legacyTechnicalOwner, Carbon::today()->addDays(14));
        $legacyTechnicalOwner->forceFill(['email' => 'temp_1736600001@avilona.ru'])->save();

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool =>
                $mail->hasTo($eligibleOwner->email)
                && $mail->booking->id === $eligibleBooking->id
                && $mail->daysUntilTrip === 3
        );

        Mail::assertQueuedCount(1);
    }

    // -----------------------------------------------------------------------
    // 10. Заявка вне окон 1/3/7/14 дней — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_booking_outside_reminder_windows_queues_no_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(5));

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 11. Заявка на верную дату, но не в статусе CONFIRMED — письмо не отправляется
    // -----------------------------------------------------------------------

    public function test_non_confirmed_booking_on_valid_date_queues_no_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(7), Booking::STATUS_PROGRESS);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 12. Назначенный менеджер/администратор не получает TripReminder
    // -----------------------------------------------------------------------

    public function test_assigned_manager_and_admin_do_not_receive_mail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);

        $booking = $this->makeBookingFor($owner, Carbon::today()->addDays(7), Booking::STATUS_CONFIRMED, $manager->id);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool =>
                $mail->hasTo($owner->email)
                && $mail->booking->id === $booking->id
        );

        Mail::assertNotQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool => $mail->hasTo($manager->email)
        );

        Mail::assertNotQueued(
            TripReminder::class,
            fn (TripReminder $mail): bool => $mail->hasTo($admin->email)
        );

        Mail::assertQueuedCount(1);
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
        Carbon $startDate,
        string $status = Booking::STATUS_CONFIRMED,
        ?int $managerId = null
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => $status,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => $startDate->toDateString(),
                'nights'              => 7,
                'adults'              => 2,
                'children'            => 0,
            ])
        );
    }
}
