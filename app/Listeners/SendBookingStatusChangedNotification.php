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

        // Отправляем письмо клиенту о изменении статуса, только если он
        // не отключил уведомления и не имеет технического email.
        $owner = $booking->user;
        if (
            $owner
            && !$owner->hasTechnicalEmail()
            && $owner->wantsEmailNotification('booking_updates')
        ) {
            Mail::to($owner->email)->queue(
                new BookingStatusChangedMail($booking, $oldStatus, $owner)
            );
        }

        // Если есть назначенный менеджер, отправляем ему тоже — с тем же
        // условием доставки.
        $manager = $booking->manager;
        if (
            $manager
            && !$manager->hasTechnicalEmail()
            && $manager->wantsEmailNotification('booking_updates')
        ) {
            Mail::to($manager->email)->queue(
                new BookingStatusChangedMail($booking, $oldStatus, $manager)
            );
        }
    }
}
