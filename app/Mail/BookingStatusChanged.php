<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public string $oldStatus;
    public User $recipient;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, string $oldStatus, User $recipient)
    {
        $this->booking = $booking;
        $this->oldStatus = $oldStatus;
        $this->recipient = $recipient;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusLabels = [
            'new' => 'Новая',
            'progress' => 'В обработке',
            'confirmed' => 'Подтверждена',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
        ];

        return new Envelope(
            subject: 'Статус заявки #' . $this->booking->id . ' изменен на "' . ($statusLabels[$this->booking->status] ?? $this->booking->status) . '" - Авилона',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.status-changed',
            with: [
                'chatUrl' => $this->booking->chatRouteFor($this->recipient),
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
