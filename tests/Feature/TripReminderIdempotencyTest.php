<?php

namespace Tests\Feature;

use App\Mail\TripReminder;
use App\Models\Booking;
use App\Models\BookingTripReminderDelivery;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Проверяет персистентную, основанную на БД идемпотентность постановки в
 * очередь TripReminder: уникальный ключ (booking_id, reminder_days,
 * trip_start_date) в booking_trip_reminder_deliveries — единственный источник
 * истины для дедупликации между запусками команды. Полная матрица настроек
 * email-предпочтений уже покрыта TripReminderEmailPreferenceTest и здесь не
 * дублируется.
 */
class TripReminderIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // 1. Первый приемлемый запуск: письмо в очереди + одна строка в реестре
    // -----------------------------------------------------------------------

    public function test_first_eligible_execution_creates_ledger_row_and_queues_mail(): void
    {
        $now = Carbon::parse('2026-07-27 09:00:00');
        Carbon::setTestNow($now);
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $startDate = Carbon::today()->addDays(7);
        $booking = $this->makeBookingFor($owner, $startDate);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueuedCount(1);

        $this->assertSame(1, BookingTripReminderDelivery::count());

        $delivery = BookingTripReminderDelivery::sole();

        $this->assertSame($booking->id, $delivery->booking_id);
        $this->assertSame(7, (int) $delivery->reminder_days);
        $this->assertSame($startDate->toDateString(), $delivery->trip_start_date->toDateString());

        // Raw storage check: the underlying column value must be exactly
        // Y-m-d, not "Y-m-d 00:00:00" — otherwise createOrFirst()'s
        // unique-key fallback lookup (a plain Y-m-d string) cannot find it.
        $this->assertSame($startDate->toDateString(), $delivery->getRawOriginal('trip_start_date'));

        $this->assertSame($owner->id, $delivery->recipient_user_id);
        $this->assertSame($owner->email, $delivery->recipient_email);
        $this->assertNotNull($delivery->claimed_at);
        $this->assertNotNull($delivery->queued_at);
        $this->assertTrue($delivery->claimed_at->equalTo($now));
        $this->assertTrue($delivery->queued_at->equalTo($now));
    }

    // -----------------------------------------------------------------------
    // 2. Повторный последовательный запуск: письмо ставится в очередь один раз
    // -----------------------------------------------------------------------

    public function test_sequential_duplicate_execution_queues_mail_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertQueuedCount(1);
        $this->assertSame(1, BookingTripReminderDelivery::count());
    }

    // -----------------------------------------------------------------------
    // 3. Уже завершённая доставка (queued_at заполнен) блокирует повтор
    // -----------------------------------------------------------------------

    public function test_pre_existing_completed_delivery_prevents_queueing(): void
    {
        $now = Carbon::parse('2026-07-27 09:00:00');
        Carbon::setTestNow($now);
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $startDate = Carbon::today()->addDays(7);
        $booking = $this->makeBookingFor($owner, $startDate);

        BookingTripReminderDelivery::create([
            'booking_id' => $booking->id,
            'reminder_days' => 7,
            'trip_start_date' => $startDate->toDateString(),
            'recipient_user_id' => $owner->id,
            'recipient_email' => $owner->email,
            'claimed_at' => $now->copy()->subMinute(),
            'queued_at' => $now->copy()->subMinute(),
        ]);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertSame(1, BookingTripReminderDelivery::count());
    }

    // -----------------------------------------------------------------------
    // 4. Незавершённый claim (queued_at = null) тоже блокирует и не удаляется
    // -----------------------------------------------------------------------

    public function test_pre_existing_unqueued_claim_blocks_queueing_and_is_not_reclaimed(): void
    {
        $now = Carbon::parse('2026-07-27 09:00:00');
        Carbon::setTestNow($now);
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $startDate = Carbon::today()->addDays(7);
        $booking = $this->makeBookingFor($owner, $startDate);

        $claimedAt = $now->copy()->subMinutes(5);

        $existing = BookingTripReminderDelivery::create([
            'booking_id' => $booking->id,
            'reminder_days' => 7,
            'trip_start_date' => $startDate->toDateString(),
            'recipient_user_id' => $owner->id,
            'recipient_email' => $owner->email,
            'claimed_at' => $claimedAt,
            'queued_at' => null,
        ]);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertSame(1, BookingTripReminderDelivery::count());

        $existing->refresh();
        $this->assertNull($existing->queued_at);
        $this->assertTrue($existing->claimed_at->equalTo($claimedAt));
    }

    // -----------------------------------------------------------------------
    // 5. Разные заявки на одну дату/окно — каждая получает своё письмо и строку
    // -----------------------------------------------------------------------

    public function test_different_bookings_each_receive_their_own_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));
        Mail::fake();

        $ownerA = $this->makeUser(Role::TOURIST);
        $bookingA = $this->makeBookingFor($ownerA, Carbon::today()->addDays(7));

        $ownerB = $this->makeUser(Role::TOURIST);
        $bookingB = $this->makeBookingFor($ownerB, Carbon::today()->addDays(7));

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 2')
            ->assertExitCode(0);

        Mail::assertQueuedCount(2);
        $this->assertSame(2, BookingTripReminderDelivery::count());
        $this->assertSame(1, BookingTripReminderDelivery::where('booking_id', $bookingA->id)->count());
        $this->assertSame(1, BookingTripReminderDelivery::where('booking_id', $bookingB->id)->count());
    }

    // -----------------------------------------------------------------------
    // 6. Разные окна напоминаний для одной заявки независимы друг от друга
    // -----------------------------------------------------------------------

    public function test_different_reminder_windows_remain_independent_for_same_booking(): void
    {
        $start = Carbon::parse('2026-08-10 00:00:00');
        Carbon::setTestNow($start->copy()->subDays(14)->setTime(9, 0));
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $start);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Carbon::setTestNow($start->copy()->subDays(7)->setTime(9, 0));

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueuedCount(2);
        Mail::assertQueued(TripReminder::class, fn (TripReminder $mail): bool => $mail->daysUntilTrip === 14);
        Mail::assertQueued(TripReminder::class, fn (TripReminder $mail): bool => $mail->daysUntilTrip === 7);

        $this->assertSame(2, BookingTripReminderDelivery::where('booking_id', $booking->id)->count());

        $reminderDays = BookingTripReminderDelivery::where('booking_id', $booking->id)
            ->orderBy('reminder_days')
            ->pluck('reminder_days')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $this->assertSame([7, 14], $reminderDays);
    }

    // -----------------------------------------------------------------------
    // 7. Перенос даты поездки открывает новое окно напоминаний
    // -----------------------------------------------------------------------

    public function test_rescheduled_booking_allows_new_reminder_for_new_start_date(): void
    {
        $originalStart = Carbon::parse('2026-08-03 00:00:00');
        Carbon::setTestNow($originalStart->copy()->subDays(7)->setTime(9, 0));
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $originalStart);

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        $newStart = Carbon::parse('2026-08-17 00:00:00');
        Booking::withoutEvents(function () use ($booking, $newStart): void {
            $booking->forceFill(['start_date' => $newStart->toDateString()])->save();
        });

        Carbon::setTestNow($newStart->copy()->subDays(7)->setTime(9, 0));

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueuedCount(2);

        $this->assertSame(2, BookingTripReminderDelivery::where('booking_id', $booking->id)->count());

        $dates = BookingTripReminderDelivery::where('booking_id', $booking->id)
            ->orderBy('trip_start_date')
            ->pluck('trip_start_date')
            ->map(fn ($value): string => Carbon::parse($value)->toDateString())
            ->all();

        $this->assertSame(
            [$originalStart->toDateString(), $newStart->toDateString()],
            $dates
        );
    }

    // -----------------------------------------------------------------------
    // 8. Неприемлемый получатель: письмо и строка реестра не создаются
    // -----------------------------------------------------------------------

    public function test_ineligible_owner_creates_no_ledger_row(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        $owner->forceFill([
            'notification_settings' => json_encode(['trip_reminders' => false]),
        ])->save();

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertSame(0, BookingTripReminderDelivery::where('booking_id', $booking->id)->count());
    }

    // -----------------------------------------------------------------------
    // 9-10. Сбой постановки в очередь удаляет свежий claim; следующий запуск
    // успешно повторяет попытку для той же логической доставки
    // -----------------------------------------------------------------------

    public function test_queue_failure_removes_fresh_claim_and_allows_later_retry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));

        $owner = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, Carbon::today()->addDays(7));

        Mail::shouldReceive('to->queue')
            ->once()
            ->andThrow(new \Exception('Simulated mail transport failure'));

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        $this->assertSame(0, BookingTripReminderDelivery::where('booking_id', $booking->id)->count());

        // Более поздний запуск с работающей отправкой должен успешно
        // повторить попытку для той же логической доставки.
        Mail::fake();

        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 1')
            ->assertExitCode(0);

        Mail::assertQueuedCount(1);

        $delivery = BookingTripReminderDelivery::where('booking_id', $booking->id)->sole();
        $this->assertNotNull($delivery->queued_at);
    }

    // -----------------------------------------------------------------------
    // 11. Уникальность на уровне БД не допускает дублей независимо от кода
    // -----------------------------------------------------------------------

    public function test_database_unique_constraint_prevents_duplicate_ledger_rows(): void
    {
        $now = Carbon::parse('2026-07-27 09:00:00');
        Carbon::setTestNow($now);

        $owner = $this->makeUser(Role::TOURIST);
        $startDate = Carbon::today()->addDays(7);
        $booking = $this->makeBookingFor($owner, $startDate);

        $attributes = [
            'booking_id' => $booking->id,
            'reminder_days' => 7,
            'trip_start_date' => $startDate->toDateString(),
            'recipient_user_id' => $owner->id,
            'recipient_email' => $owner->email,
            'claimed_at' => $now,
            'queued_at' => null,
        ];

        BookingTripReminderDelivery::create($attributes);

        // The duplicate insert is attempted inside its own nested
        // transaction/savepoint so that the unique-constraint failure only
        // rolls back the savepoint, not RefreshDatabase's outer transaction
        // wrapping this test — otherwise the connection would be left unable
        // to serve the assertions below.
        $duplicateRejected = false;

        try {
            DB::transaction(function () use ($attributes): void {
                BookingTripReminderDelivery::create($attributes);
            });
        } catch (\Throwable $e) {
            // Наблюдаемое конечное состояние важнее конкретного класса
            // исключения — проверяем ниже, что дубль не сохранился.
            $duplicateRejected = true;
        }

        $this->assertTrue(
            $duplicateRejected,
            'Expected the duplicate insert to be rejected by the unique constraint.'
        );

        $this->assertSame(
            1,
            BookingTripReminderDelivery::where('booking_id', $booking->id)
                ->where('reminder_days', 7)
                ->where('trip_start_date', $startDate->toDateString())
                ->count()
        );
    }

    // -----------------------------------------------------------------------
    // 12. Планировщик: ежедневно в 10:00, withoutOverlapping(60)
    // -----------------------------------------------------------------------

    public function test_command_is_scheduled_daily_with_a_sixty_minute_overlap_guard(): void
    {
        // Resolving the console kernel triggers Kernel::defineConsoleSchedule(),
        // which registers the Schedule singleton via our schedule() method.
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event): bool => str_contains($event->command, 'bookings:send-trip-reminders'));

        $this->assertNotNull($event, 'Expected bookings:send-trip-reminders to be scheduled.');
        $this->assertSame('0 10 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(60, $event->expiresAt);
    }

    // -----------------------------------------------------------------------
    // 13. Mail::queue() успевает выполниться, но фиксация queued_at падает:
    // claim сохраняется незавершённым и блокирует повторную отправку
    // -----------------------------------------------------------------------

    public function test_queued_at_finalization_failure_keeps_claim_and_blocks_duplicate_retry(): void
    {
        $now = Carbon::parse('2026-07-27 09:00:00');
        Carbon::setTestNow($now);
        Mail::fake();

        $owner = $this->makeUser(Role::TOURIST);
        $startDate = Carbon::today()->addDays(7);
        $booking = $this->makeBookingFor($owner, $startDate);

        // Fail only the write that sets a non-null queued_at on an already
        // persisted row (the finalization save) — not the initial claim
        // insert, where the model does not yet exist.
        BookingTripReminderDelivery::saving(function (BookingTripReminderDelivery $model): void {
            if ($model->exists && $model->isDirty('queued_at') && $model->queued_at !== null) {
                throw new \RuntimeException('Simulated ledger-finalization failure');
            }
        });

        try {
            $this->artisan('bookings:send-trip-reminders')
                ->expectsOutputToContain('Всего отправлено напоминаний: 1')
                ->expectsOutputToContain(
                    "Ошибка фиксации доставки для заявки #{$booking->id}: Simulated ledger-finalization failure"
                )
                ->assertExitCode(0);
        } finally {
            // Remove the simulated hook immediately so it cannot leak into
            // the assertions or command run below, or into other tests.
            BookingTripReminderDelivery::flushEventListeners();
        }

        Mail::assertQueuedCount(1);
        $this->assertSame(1, BookingTripReminderDelivery::count());

        $delivery = BookingTripReminderDelivery::where('booking_id', $booking->id)->sole();
        $this->assertNull($delivery->queued_at);
        $this->assertNotNull($delivery->claimed_at);
        $claimedAt = $delivery->claimed_at;

        // A second run for the same logical reminder must be blocked by the
        // retained unqueued claim: no automatic stale-claim recovery in this
        // slice, and Mail::queue() success already counted as one sent
        // reminder on the first run.
        $this->artisan('bookings:send-trip-reminders')
            ->expectsOutputToContain('Всего отправлено напоминаний: 0')
            ->assertExitCode(0);

        Mail::assertQueuedCount(1);
        $this->assertSame(1, BookingTripReminderDelivery::count());

        $delivery->refresh();
        $this->assertNull($delivery->queued_at);
        $this->assertTrue($delivery->claimed_at->equalTo($claimedAt));
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
                'user_id' => $owner->id,
                'manager_id' => $managerId,
                'status' => $status,
                'departure_city' => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city' => 'Antalya',
                'start_date' => $startDate->toDateString(),
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
            ])
        );
    }
}
