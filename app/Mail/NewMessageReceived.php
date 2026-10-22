<?php

namespace App\Mail;

use App\Mail\Concerns\SkipsStaleChatRecipient;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Письмо хранит только id сообщения, а не модель (AOA-01-I5): сообщение могли удалить,
 * пока письмо ждало воркера. Модель в очереди (SerializesModels) при отсутствии записи
 * падает ModelNotFoundException ещё до send() и оставляет failed job; здесь запись
 * читается при доставке, а её отсутствие — ожидаемое условие «отправлять нечего»
 * (см. SkipsStaleChatRecipient). Текст удалённого сообщения письмо восстановить не может.
 */
class NewMessageReceived extends Mailable
{
    use Queueable, SkipsStaleChatRecipient;

    public int $messageId;

    /** Читается только при доставке; в сериализованное письмо не попадает. */
    private ?Message $resolved = null;

    /**
     * Create a new message instance.
     */
    public function __construct(Message $message)
    {
        $this->messageId = (int) $message->getKey();
    }

    /** Актуальное сообщение из БД; null — оно удалено. */
    public function message(): ?Message
    {
        return $this->resolved ??= Message::query()
            ->with(['booking', 'sender', 'receiver'])
            ->find($this->messageId);
    }

    /** В очередь уходят только скаляры: кэш прочитанного сообщения не сериализуется. */
    public function __sleep(): array
    {
        return array_values(array_diff(array_keys(get_object_vars($this)), ['resolved']));
    }

    protected function deliveryBooking(): ?Booking
    {
        return $this->message()?->booking;
    }

    protected function deliveryRecipient(): ?User
    {
        return $this->message()?->receiver;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новое сообщение по заявке #' . $this->message()->booking_id . ' - Авилона',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $message = $this->message();

        return new Content(
            view: 'emails.messages.new-message',
            with: [
                // Модель передаётся под именем chatMessage: переменная $message
                // зарезервирована мейлером (Illuminate\Mail\Message) и в шаблоне
                // затирает публичное свойство.
                'chatMessage' => $message,
                'chatUrl' => $message->booking->chatRouteFor($message->receiver),
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
