<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public Message $message;

    /**
     * Create a new message instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новое сообщение по заявке #' . $this->message->booking_id . ' - Авилона',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.messages.new-message',
            with: [
                // Модель передаётся под именем chatMessage: переменная $message
                // зарезервирована мейлером (Illuminate\Mail\Message) и в шаблоне
                // затирает публичное свойство.
                'chatMessage' => $this->message,
                'chatUrl' => $this->message->booking->chatRouteFor($this->message->receiver),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
