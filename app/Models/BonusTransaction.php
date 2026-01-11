<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bonus_account_id',
        'type',
        'amount',
        'reason',
        'booking_id',
        'balance_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Связь с бонусным счетом
     */
    public function bonusAccount()
    {
        return $this->belongsTo(BonusAccount::class);
    }

    /**
     * Связь с заявкой (если применимо)
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
