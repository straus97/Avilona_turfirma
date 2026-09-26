<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

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
