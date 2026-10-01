<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Входящее обращение внешнего провайдера (пока только Tourvisor).
 *
 * Это НЕ Booking: у обращения нет владельца-пользователя, оно не влияет на
 * деньги и статусы. Контактные поля (имя/телефон/email) — данные из выгрузки
 * провайдера и НЕ используются для определения аккаунта.
 */
class IncomingInquiry extends Model
{
    public const PROVIDER_TOURVISOR = 'tourvisor';

    public const STATE_PENDING = 'pending';
    public const STATE_IMPORTING = 'importing';
    public const STATE_IMPORTED = 'imported';
    public const STATE_FAILED = 'failed';
    public const STATE_UNSUPPORTED = 'unsupported';

    /**
     * Бизнес-состояние обработки (E5-A3). Не смешивать с техническим state:
     * тот отвечает только за загрузку данных из провайдера.
     */
    public const WORKFLOW_NEW = 'new';
    public const WORKFLOW_IN_PROGRESS = 'in_progress';
    public const WORKFLOW_CONVERTED = 'converted';
    public const WORKFLOW_CLOSED = 'closed';

    /** Сколько «importing» считается зависшим (процесс упал до записи результата). */
    public const IMPORT_CLAIM_TTL_MINUTES = 5;

    /**
     * Массовое присваивание закрыто целиком: записи создаёт только код
     * TourvisorInquiryIntake / TourvisorInquiryImporter через forceFill.
     */
    protected $guarded = ['*'];

    /** Сырой снимок ответа — только для служебной сверки, не для сериализации. */
    protected $hidden = ['vendor_payload'];

    protected $casts = [
        'external_type' => 'integer',
        'attempts' => 'integer',
        'webhook_count' => 'integer',
        'first_notified_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'imported_at' => 'datetime',
        'last_error_at' => 'datetime',
        'price' => 'decimal:2',
        'fly_date' => 'date',
        'nights' => 'integer',
        'vendor_payload' => 'array',
        'normalizer_version' => 'integer',
        'claimed_at' => 'datetime',
        'client_linked_at' => 'datetime',
        'converted_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Аккаунт туриста, явно выбранный сотрудником (никогда не выводится из контактов). */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Созданная заявка; удалённая (soft delete) остаётся видимой как источник. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** Данные загружены и тип поддерживается — только такое обращение можно обрабатывать. */
    public function scopeActionable(Builder $query): Builder
    {
        return $query->where('state', self::STATE_IMPORTED)->where('external_type', 0);
    }

    public function isActionable(): bool
    {
        return $this->state === self::STATE_IMPORTED && $this->external_type === 0;
    }

    public function workflowLabel(): string
    {
        return match ($this->workflow_state) {
            self::WORKFLOW_NEW => 'Новое',
            self::WORKFLOW_IN_PROGRESS => 'В работе',
            self::WORKFLOW_CONVERTED => 'Оформлено',
            self::WORKFLOW_CLOSED => 'Закрыто',
            default => (string) $this->workflow_state,
        };
    }

    /** @return array<string, string> значение => подпись (для фильтров) */
    public static function workflowLabels(): array
    {
        return [
            self::WORKFLOW_NEW => 'Новые',
            self::WORKFLOW_IN_PROGRESS => 'В работе',
            self::WORKFLOW_CONVERTED => 'Оформлены',
            self::WORKFLOW_CLOSED => 'Закрыты',
        ];
    }

    /** Русская подпись состояния для служебных экранов. */
    public function stateLabel(): string
    {
        return match ($this->state) {
            self::STATE_PENDING => 'Ожидает загрузки',
            self::STATE_IMPORTING => 'Загружается',
            self::STATE_IMPORTED => 'Загружено',
            self::STATE_FAILED => 'Ошибка загрузки',
            self::STATE_UNSUPPORTED => 'Тип не обрабатывается',
            default => (string) $this->state,
        };
    }

    public function isOnline(): bool
    {
        return $this->external_type === 1;
    }
}
