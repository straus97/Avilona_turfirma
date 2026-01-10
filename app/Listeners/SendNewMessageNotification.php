<?php

namespace App\Listeners;

use App\Events\NewMessageReceived;
use App\Mail\NewMessageReceived as NewMessageReceivedMail;
use Illuminate\Support\Facades\Mail;

class SendNewMessageNotification
{
    /**
     * Handle the event.
     */
    public function handle(NewMessageReceived $event): void
    {
        $message = $event->message;

        // Отправляем письмо получателю сообщения
        if ($message->receiver) {
            Mail::to($message->receiver->email)->queue(new NewMessageReceivedMail($message));
        }
    }
}
