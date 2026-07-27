<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTripReminderDelivery extends Model
{
    protected $fillable = [
        'booking_id',
        'reminder_days',
        'trip_start_date',
        'recipient_user_id',
        'recipient_email',
        'claimed_at',
        'queued_at',
    ];

    protected $casts = [
        'trip_start_date' => 'date',
        'claimed_at' => 'datetime',
        'queued_at' => 'datetime',
    ];

    /**
     * Always persist trip_start_date as an exact Y-m-d string, independent of
     * the connection's date format. Without this, SQLite stores the 'date'
     * cast as "Y-m-d 00:00:00", which breaks createOrFirst()'s unique-key
     * fallback lookup against a plain "Y-m-d" identity value.
     */
    public function setTripStartDateAttribute(mixed $value): void
    {
        $this->attributes['trip_start_date'] = $value === null
            ? null
            : Carbon::parse($value)->format('Y-m-d');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
