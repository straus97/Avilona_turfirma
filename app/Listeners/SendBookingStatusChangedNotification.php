<?php

namespace App\Listeners;

use App\Events\BookingStatusChanged;
use App\Mail\BookingStatusChanged as BookingStatusChangedMail;
use Illuminate\Support\Facades\Mail;

class SendBookingStatusChangedNotification
{
    /**
     * Handle the event.
     */
    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking;
        $oldStatus = $event->oldStatus;

        // Отправляем письмо клиенту о изменении статуса
        Mail::to($booking->user->email)->queue(
            new BookingStatusChangedMail($booking, $oldStatus)
        );

        // Если есть назначенный менеджер, отправляем ему тоже
        if ($booking->manager) {
            Mail::to($booking->manager->email)->queue(
                new BookingStatusChangedMail($booking, $oldStatus)
            );
        }
    }
}
