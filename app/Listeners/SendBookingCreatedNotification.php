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

        // Письмо клиенту содержит временный пароль, поэтому оно не отправляется
        // на технический (сгенерированный) адрес — такого почтового ящика нет.
        if (!$booking->user->hasTechnicalEmail()) {
            Mail::to($booking->user->email)->queue(new BookingCreatedMail($booking));
        }

        // Отправляем отдельное письмо всем администраторам
        $admins = User::whereHas('roles', function($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new \App\Mail\AdminBookingCreated($booking));
        }
    }
}
