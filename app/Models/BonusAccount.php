<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'level',
        'total_earned',
        'total_spent',
        'referral_code',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_spent' => 'decimal:2',
    ];

    /**
     * Связь с пользователем
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Связь с транзакциями
     */
    public function transactions()
    {
        return $this->hasMany(BonusTransaction::class);
    }

    /**
     * Начисление бонусов
     */
    public function earn(float $amount, string $reason, ?int $bookingId = null)
    {
        $this->balance += $amount;
        $this->total_earned += $amount;
        $this->save();

        $this->transactions()->create([
            'type' => 'earn',
            'amount' => $amount,
            'reason' => $reason,
            'booking_id' => $bookingId,
            'balance_after' => $this->balance,
        ]);
    }

    /**
     * Списание бонусов
     */
    public function spend(float $amount, string $reason, ?int $bookingId = null)
    {
        if ($this->balance < $amount) {
            throw new \Exception('Недостаточно бонусов на балансе');
        }

        $this->balance -= $amount;
        $this->total_spent += $amount;
        $this->save();

        $this->transactions()->create([
            'type' => 'spend',
            'amount' => $amount,
            'reason' => $reason,
            'booking_id' => $bookingId,
            'balance_after' => $this->balance,
        ]);
    }
}
