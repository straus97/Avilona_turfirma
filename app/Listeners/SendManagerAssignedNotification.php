<?php

namespace App\Listeners;

use App\Events\ManagerAssigned;
use App\Mail\ManagerAssigned as ManagerAssignedMail;
use Illuminate\Support\Facades\Mail;

class SendManagerAssignedNotification
{
    /**
     * Handle the event.
     */
    public function handle(ManagerAssigned $event): void
    {
        $booking = $event->booking;

        // Отправляем письмо клиенту о назначении менеджера
        Mail::to($booking->user->email)->queue(new ManagerAssignedMail($booking));

        // Отправляем уведомление менеджеру о новой заявке
        if ($booking->manager) {
            // Можно создать отдельный шаблон для менеджера, пока используем тот же
            Mail::to($booking->manager->email)->queue(new ManagerAssignedMail($booking));
        }
    }
}
