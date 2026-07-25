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
        $receiver = $message->receiver;

        // Отправляем письмо только реальному получателю, у которого нет
        // технического адреса и который не отключил уведомления о сообщениях.
        if (
            $receiver
            && !$receiver->hasTechnicalEmail()
            && $receiver->wantsEmailNotification('new_messages')
        ) {
            Mail::to($receiver->email)->queue(new NewMessageReceivedMail($message));
        }
    }
}
