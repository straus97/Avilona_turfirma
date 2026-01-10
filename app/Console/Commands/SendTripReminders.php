<?php

namespace App\Console\Commands;

use App\Mail\TripReminder;
use App\Models\Booking;
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
                try {
                    Mail::to($booking->user->email)->queue(
                        new TripReminder($booking, $days)
                    );
                    
                    $totalSent++;
                    $this->info("Отправлено напоминание для заявки #{$booking->id} (через {$days} дней)");
                } catch (\Exception $e) {
                    $this->error("Ошибка отправки для заявки #{$booking->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Всего отправлено напоминаний: {$totalSent}");
        return Command::SUCCESS;
    }
}
