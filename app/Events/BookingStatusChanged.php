<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Booking $booking;
    public string $oldStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(Booking $booking, string $oldStatus)
    {
        $this->booking = $booking;
        $this->oldStatus = $oldStatus;
    }
}
