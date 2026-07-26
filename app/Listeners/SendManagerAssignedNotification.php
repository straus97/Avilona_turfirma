<?php

namespace App\Listeners;

use App\Events\ManagerAssigned;
use App\Mail\ManagerAssigned as ManagerAssignedMail;
use App\Models\User;
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
        if ($this->wantsManagerAssignedMail($booking->user)) {
            Mail::to($booking->user->email)->queue(new ManagerAssignedMail($booking, $booking->user));
        }

        // Отправляем уведомление менеджеру о новой заявке
        if ($booking->manager && $this->wantsManagerAssignedMail($booking->manager)) {
            // Можно создать отдельный шаблон для менеджера, пока используем тот же
            Mail::to($booking->manager->email)->queue(new ManagerAssignedMail($booking, $booking->manager));
        }
    }

    /**
     * Разрешена ли отправка письма о назначении менеджера данному получателю.
     */
    private function wantsManagerAssignedMail(?User $recipient): bool
    {
        return $recipient
            && !$recipient->hasTechnicalEmail()
            && $recipient->wantsEmailNotification('booking_updates');
    }
}
