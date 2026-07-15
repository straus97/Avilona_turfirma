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
     * Связь с документами по заявке
     */
    public function bookingDocuments(): HasMany
    {
        return $this->hasMany(BookingDocument::class);
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

    // -----------------------------------------------------------------------
    // Transition matrix
    // -----------------------------------------------------------------------

    /**
     * Authoritative status transition map.
     * key   = current status
     * value = list of reachable target statuses
     */
    public static function transitionMap(): array
    {
        return [
            self::STATUS_NEW       => [self::STATUS_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_PROGRESS  => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED => [],
            self::STATUS_COMPLETED => [],
        ];
    }

    /**
     * Whether this booking can transition to the given status.
     * Checks the transition matrix plus the PROGRESS invariant
     * (manager_id must be set before moving into PROGRESS).
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        // Same status is never a real transition.
        if ($targetStatus === $this->status) {
            return false;
        }

        $allowed = self::transitionMap()[$this->status] ?? [];

        if (!in_array($targetStatus, $allowed, true)) {
            return false;
        }

        // PROGRESS requires an assigned manager.
        if ($targetStatus === self::STATUS_PROGRESS && !$this->manager_id) {
            return false;
        }

        return true;
    }

    /**
     * Status values that may be submitted via the generic update endpoint:
     * the current status (no-op) plus every reachable transition.
     *
     * @return string[]
     */
    public function allowedStatusesForUpdate(): array
    {
        $transitions = array_filter(
            self::transitionMap()[$this->status] ?? [],
            fn (string $s): bool => $this->canTransitionTo($s)
        );

        return array_values(array_unique(array_merge([$this->status], $transitions)));
    }

    /**
     * Perform a validated status transition.
     *
     * Dispatches BookingStatusChanged after a successful change.
     * For cancellation, restores tour seats before firing the event.
     *
     * Side-effect order for a successful transition:
     *   1. validate transition;
     *   2. capture old status;
     *   3. update booking status;
     *   4. if target is CANCELLED and tour is set, restore seats;
     *   5. dispatch BookingStatusChanged.
     *
     * @throws \DomainException when the transition is not allowed.
     */
    public function transitionTo(string $targetStatus): void
    {
        if (!$this->canTransitionTo($targetStatus)) {
            throw new \DomainException(
                "Invalid booking status transition from '{$this->status}' to '{$targetStatus}'."
            );
        }

        $oldStatus = $this->status;

        $this->update(['status' => $targetStatus]);

        if ($targetStatus === self::STATUS_CANCELLED && $this->tour) {
            $this->tour->increaseAvailableSeats($this->total_tourists);
        }

        event(new \App\Events\BookingStatusChanged($this, $oldStatus));
    }

    // -----------------------------------------------------------------------
    // Методы
    // -----------------------------------------------------------------------

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
        $this->transitionTo(self::STATUS_CONFIRMED);
    }

    public function cancel(): void
    {
        $this->transitionTo(self::STATUS_CANCELLED);
    }

    public function complete(): void
    {
        $this->transitionTo(self::STATUS_COMPLETED);
    }

    /**
     * Активный маршрут чата по заявке для конкретного получателя письма.
     *
     * Владелец заявки (турист) → личный чат кабинета; текущий ответственный
     * (менеджер ИЛИ администратор в manager_id) → чат менеджера. Любой другой
     * пользователь (в т.ч. прежний ответственный) отклоняется — ссылка из
     * письма не должна вести неучастника в чужой чат.
     */
    public function chatRouteFor(User $recipient): string
    {
        if ($recipient->id === $this->user_id) {
            return route('cabinet.chat', $this->id);
        }

        if ($recipient->id === $this->manager_id) {
            return route('cabinet.manager.chat', ['bookingId' => $this->id]);
        }

        throw new \LogicException(
            "User {$recipient->id} is neither the owner nor the current assignee of booking {$this->id}."
        );
    }
}
