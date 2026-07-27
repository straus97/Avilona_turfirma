<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewMessageDatabaseNotification extends Notification
{
    public function __construct(protected Message $message)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $hasAttachment = $this->message->hasAttachment();
        $text = trim((string) $this->message->message);

        if ($text !== '') {
            $preview = Str::limit($text, 120);
        } elseif ($hasAttachment) {
            $preview = 'Новое вложение';
        } else {
            $preview = 'Новое сообщение';
        }

        return [
            'type' => 'new_message',
            'message_id' => (int) $this->message->id,
            'booking_id' => (int) $this->message->booking_id,
            'sender_id' => (int) $this->message->sender_id,
            'sender_name' => $this->message->sender->name ?? 'Пользователь',
            'preview' => $preview,
            'has_attachment' => $hasAttachment,
        ];
    }
}
