<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'sender_id',
        'receiver_id',
        'message',
        'attachment_url',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Скрываем внутренний путь вложения из любой сериализации,
     * чтобы приватный путь в хранилище не попадал в JSON/клиент.
     */
    protected $hidden = [
        'attachment_url',
    ];

    /**
     * Отношения
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scopes
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByBooking($query, $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }

    /**
     * Сообщения по заявкам, доступным пользователю СЕЙЧАС (AOA-02): администратор — все
     * (надзор), менеджер — по заявкам, где он ответственный, турист — по своим; остальным
     * ничего. Единое правило для всех счётчиков непрочитанного: после переназначения
     * прежний ответственный не видит ни числа, ни содержимого чужой переписки.
     */
    public function scopeAccessibleTo($query, User $user)
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $query;
        }

        if ($user->hasRole(User::ROLE_MANAGER)) {
            return $query->whereHas('booking', fn ($q) => $q->where('manager_id', $user->id));
        }

        if ($user->hasRole(User::ROLE_TOURIST)) {
            return $query->whereHas('booking', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeBySender($query, $senderId)
    {
        return $query->where('sender_id', $senderId);
    }

    public function scopeByReceiver($query, $receiverId)
    {
        return $query->where('receiver_id', $receiverId);
    }

    /**
     * Методы
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function hasAttachment(): bool
    {
        return !empty($this->attachment_url);
    }

    /**
     * Защищённый URL для скачивания вложения.
     *
     * Не входит в $appends: попадает в JSON только через ChatMessagePresenter
     * (MessageController::index/store), чтобы не генерировать маршрут при любой
     * сериализации Message.
     */
    public function getAttachmentDownloadUrlAttribute(): ?string
    {
        return $this->hasAttachment()
            ? route('messages.attachment', $this)
            : null;
    }
}
