<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запрос менеджера на проверку клиента (AOA-01-I7).
 *
 * Запись создаёт и меняет только ClientVerificationService. Менеджер видит
 * исключительно собственные запросы и только нейтральное состояние: «на проверке»,
 * «подтверждён», «отклонён». Способ решения (привязан существующий / создан новый)
 * и идентификатор аккаунта менеджеру не раскрываются.
 */
class ClientVerificationRequest extends Model
{
    public const CONTEXT_BOOKING_FORM = 'booking_form';
    public const CONTEXT_INCOMING_INQUIRY = 'incoming_inquiry';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const RESOLUTION_LINKED_EXISTING = 'linked_existing';
    public const RESOLUTION_CREATED_NEW = 'created_new';
    public const RESOLUTION_REJECTED = 'rejected';

    /** Сколько дней менеджер видит подтверждённого клиента без заявки/обращения. */
    public const ACCESS_DAYS = 30;

    protected $guarded = ['*'];

    protected $casts = [
        'resolved_at' => 'datetime',
        'access_expires_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IncomingInquiry::class, 'incoming_inquiry_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function resolvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Нейтральная подпись для менеджера: одинакова для привязки и создания. */
    public function managerStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'На проверке у администратора',
            self::STATUS_APPROVED => 'Клиент подтверждён',
            self::STATUS_REJECTED => 'Отклонён',
            default => $this->status,
        };
    }

    public function contextLabel(): string
    {
        return match ($this->context_type) {
            self::CONTEXT_INCOMING_INQUIRY => 'Входящее обращение' . ($this->incoming_inquiry_id ? ' №' . $this->incoming_inquiry_id : ''),
            default => 'Оформление заявки',
        };
    }

    public function resolutionLabel(): string
    {
        return match ($this->resolution) {
            self::RESOLUTION_LINKED_EXISTING => 'Привязан существующий аккаунт',
            self::RESOLUTION_CREATED_NEW => 'Создан новый аккаунт',
            self::RESOLUTION_REJECTED => 'Отклонён',
            default => '—',
        };
    }
}
