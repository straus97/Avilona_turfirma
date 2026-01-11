<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingCreated as BookingCreatedMail;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class SendBookingCreatedNotification
{
    /**
     * Handle the event.
     */
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Отправляем письмо клиенту (туристу)
        Mail::to($booking->user->email)->queue(new BookingCreatedMail($booking));

        // Отправляем отдельное письмо всем администраторам
        $admins = User::whereHas('roles', function($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new \App\Mail\AdminBookingCreated($booking));
        }
    }
}
