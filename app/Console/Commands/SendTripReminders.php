<?php

namespace App\Console\Commands;

use App\Mail\TripReminder;
use App\Models\Booking;
use App\Models\BookingTripReminderDelivery;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTripReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-trip-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправка напоминаний о предстоящих поездках';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Начинаем отправку напоминаний о поездках...');

        // Дни для отправки напоминаний
        $reminderDays = [1, 3, 7, 14];
        $totalSent = 0;

        foreach ($reminderDays as $days) {
            $targetDate = Carbon::today()->addDays($days);

            $bookings = Booking::with(['user', 'manager'])
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('start_date', $targetDate)
                ->get();

            foreach ($bookings as $booking) {
                $owner = $booking->user;

                if (
                    !$owner
                    || $owner->hasTechnicalEmail()
                    || !$owner->wantsEmailNotification('trip_reminders')
                ) {
                    continue;
                }

                // The (booking_id, reminder_days, trip_start_date) unique constraint
                // is the authoritative duplicate guard, not this PHP process.
                // createOrFirst() attempts an insert and, on a unique-key race from a
                // concurrent run, falls back to reading the row the other process
                // created — there is no read-then-create gap to race through.
                $delivery = BookingTripReminderDelivery::createOrFirst(
                    [
                        'booking_id' => $booking->id,
                        'reminder_days' => $days,
                        'trip_start_date' => $booking->start_date->toDateString(),
                    ],
                    [
                        'recipient_user_id' => $owner->id,
                        'recipient_email' => $owner->email,
                        'claimed_at' => Carbon::now(),
                        'queued_at' => null,
                    ]
                );

                if (!$delivery->wasRecentlyCreated) {
                    // A pre-existing row — queued or still unqueued — blocks another
                    // attempt. An unqueued row may belong to another active process
                    // or an interrupted run; this slice does not reclaim it.
                    continue;
                }

                try {
                    Mail::to($owner->email)->queue(
                        new TripReminder($booking, $days)
                    );
                } catch (\Exception $e) {
                    // Only the claim this run just created is removed, so a later
                    // run can retry the same logical reminder.
                    $delivery->delete();
                    $this->error("Ошибка отправки для заявки #{$booking->id}: " . $e->getMessage());
                    continue;
                }

                // Mail::queue() has already returned successfully at this point, so
                // the reminder counts as sent even if finalizing queued_at below
                // fails. We do not attempt an exactly-once guarantee spanning the
                // mail transport and this row, and Mail::queue() is deliberately
                // kept outside of any SQL transaction.
                try {
                    $delivery->forceFill(['queued_at' => Carbon::now()])->save();
                } catch (\Exception $e) {
                    // Keep the claim: retaining it is safer than risking a duplicate
                    // email on a later run.
                    $this->error("Ошибка фиксации доставки для заявки #{$booking->id}: " . $e->getMessage());
                }

                $totalSent++;
                $this->info("Отправлено напоминание для заявки #{$booking->id} (через {$days} дней)");
            }
        }

        $this->info("Всего отправлено напоминаний: {$totalSent}");
        return Command::SUCCESS;
    }
}
