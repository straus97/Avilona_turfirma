<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripReminder extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public int $daysUntilTrip;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, int $daysUntilTrip)
    {
        $this->booking = $booking;
        $this->daysUntilTrip = $daysUntilTrip;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Напоминание о поездке через ' . $this->daysUntilTrip . ' ' . str_plural($this->daysUntilTrip, 'день', 'дня', 'дней') . ' - Авилона',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.trip-reminder',
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
