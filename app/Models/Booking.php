<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => \App\Events\BookingCreated::class,
    ];

    // Константы для статусов
    const STATUS_NEW = 'new';
    const STATUS_PROGRESS = 'progress';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'tour_id',
        'manager_id',
        'status',
        'departure_city',
        'destination_country',
        'destination_city',
        'start_date',
        'start_date_end',
        'nights',
        'nights_max',
        'adults',
        'children',
        'children_ages',
        'tourists_data',
        'total_price',
        'paid_amount',
        'notes',
        'manager_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'start_date_end' => 'date',
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'tourists_data' => 'array',
        'children_ages' => 'array',
    ];

    /**
     * Отношения
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Scopes
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByManager($query, $managerId)
    {
        return $query->where('manager_id', $managerId);
    }

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_PROGRESS);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Accessors
     */
    public function getTotalTouristsAttribute()
    {
        return $this->adults + $this->children;
    }

    public function getFormattedTotalPriceAttribute()
    {
        return number_format($this->total_price, 0, ',', ' ') . ' ₽';
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->paid_amount >= $this->total_price;
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total_price - $this->paid_amount);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_NEW => 'Новая',
            self::STATUS_PROGRESS => 'В обработке',
            self::STATUS_CONFIRMED => 'Подтверждена',
            self::STATUS_CANCELLED => 'Отменена',
            self::STATUS_COMPLETED => 'Завершена',
            default => 'Неизвестно',
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_NEW => 'primary',
            self::STATUS_PROGRESS => 'warning',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_COMPLETED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Методы
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_NEW => 'Новая',
            self::STATUS_PROGRESS => 'В обработке',
            self::STATUS_CONFIRMED => 'Подтверждена',
            self::STATUS_CANCELLED => 'Отменена',
            self::STATUS_COMPLETED => 'Завершена',
        ];
    }

    public function assignManager(int $managerId): void
    {
        $this->update([
            'manager_id' => $managerId,
            'status' => self::STATUS_PROGRESS,
        ]);
        
        // Отправляем событие о назначении менеджера
        event(new \App\Events\ManagerAssigned($this));
    }

    public function confirm(): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => self::STATUS_CONFIRMED]);
        
        // Отправляем событие об изменении статуса
        event(new \App\Events\BookingStatusChanged($this, $oldStatus));
    }

    public function cancel(): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => self::STATUS_CANCELLED]);
        
        // Возвращаем места в тур
        if ($this->tour) {
            $this->tour->increaseAvailableSeats($this->total_tourists);
        }
        
        // Отправляем событие об изменении статуса
        event(new \App\Events\BookingStatusChanged($this, $oldStatus));
    }

    public function complete(): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => self::STATUS_COMPLETED]);
        
        // Отправляем событие об изменении статуса
        event(new \App\Events\BookingStatusChanged($this, $oldStatus));
    }
}
