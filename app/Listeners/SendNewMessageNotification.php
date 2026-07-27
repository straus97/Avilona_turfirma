<?php

namespace App\Listeners;

use App\Events\NewMessageReceived;
use App\Mail\NewMessageReceived as NewMessageReceivedMail;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Support\Facades\Log;
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

        // Персистентное уведомление в БД сохраняется независимо от email-настроек
        // получателя (email_notifications/new_messages/технический email) —
        // это отдельный канал, не подчинённый почтовым предпочтениям.
        if ($receiver) {
            try {
                $receiver->notify(new NewMessageDatabaseNotification($message));
            } catch (\Throwable $e) {
                Log::error('Failed to store new message database notification', [
                    'message_id' => $message->id,
                    'booking_id' => $message->booking_id,
                    'receiver_id' => $receiver->id,
                    'exception' => get_class($e),
                ]);
            }
        }

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
