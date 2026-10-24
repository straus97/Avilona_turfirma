<?php

namespace App\Models;

use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Обращение агентства (AOA-03-I1): бизнес-сущность, не зависящая от провайдера.
 *
 * Три разные сущности не смешиваются:
 *  - IncomingInquiry — техническое внешнее поступление (провайдер, дедупликация);
 *    здесь оно только источник происхождения (incoming_inquiry_id);
 *  - Inquiry — рабочее обращение агентства: ответственный сотрудник, статус, клиент,
 *    черновик поездки;
 *  - Booking — заявка; создаётся только явной конверсией (AOA-05), никогда из черновика.
 *
 * I1 — только фундамент: runtime по-прежнему работает с IncomingInquiry, перенос
 * существующих записей (AOA-03-I2) и ручное создание/назначение (AOA-04) не реализованы.
 * Пользовательские связи НЕ сериализуются (см. $hidden): при выдаче в JSON всегда
 * используйте явную проекцию, никогда не отдавайте User целиком.
 */
class Inquiry extends Model
{
    use HasFactory;

    public const ORIGIN_MANAGER_CREATED = 'manager_created';
    public const ORIGIN_ADMIN_CREATED = 'admin_created';
    public const ORIGIN_EXTERNAL_INTAKE = 'external_intake';

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CLOSED = 'closed';

    /** Поля черновика поездки — единственные, которые сохраняет InquiryDraftStore. */
    public const DRAFT_FIELDS = [
        'destination_country',
        'destination_city',
        'departure_city',
        'date_from',
        'date_to',
        'nights_min',
        'nights_max',
        'adults',
        'children',
        'children_ages',
        'preferences',
        'internal_notes',
    ];

    /** Атрибуты, при изменении которых перепроверяются инварианты. */
    private const INVARIANT_ATTRIBUTES = [
        'origin', 'status', 'incoming_inquiry_id', 'created_by', 'assigned_to',
        'booking_id', 'converted_at', 'closed_at',
    ];

    /**
     * Массовое присваивание закрыто целиком: записи создают только доверенные сервисы
     * (AOA-04) и фабрика тестов через forceFill; идентичность владельца нельзя
     * передать полями запроса.
     */
    protected $guarded = ['*'];

    /**
     * Ни одна связь не сериализуется неявно: User-модель содержит e-mail, телефон и
     * прочее. Заметки сотрудников тоже не уходят в JSON без явной проекции.
     */
    protected $hidden = [
        'internal_notes',
        'incomingInquiry', 'creator', 'assignee', 'clientUser',
        'clientLinkedBy', 'booking', 'convertedBy', 'closedBy', 'draftUpdatedBy',
    ];

    protected $casts = [
        'incoming_inquiry_id' => 'integer',
        'created_by' => 'integer',
        'assigned_to' => 'integer',
        'client_user_id' => 'integer',
        'booking_id' => 'integer',
        'assigned_at' => 'datetime',
        'client_linked_at' => 'datetime',
        'converted_at' => 'datetime',
        'closed_at' => 'datetime',
        'date_from' => 'date',
        'date_to' => 'date',
        'nights_min' => 'integer',
        'nights_max' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'children_ages' => 'array',
        'draft_revision' => 'integer',
        'draft_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Inquiry $inquiry): void {
            if (! $inquiry->exists || $inquiry->isDirty(self::INVARIANT_ATTRIBUTES)) {
                $inquiry->assertInvariants();
            }
        });
    }

    /** @return list<string> */
    public static function origins(): array
    {
        return [self::ORIGIN_MANAGER_CREATED, self::ORIGIN_ADMIN_CREATED, self::ORIGIN_EXTERNAL_INTAKE];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_NEW, self::STATUS_IN_PROGRESS, self::STATUS_CONVERTED, self::STATUS_CLOSED];
    }

    /** Источник — внешнее поступление (IncomingInquiry), без собственного создателя. */
    public function incomingInquiry(): BelongsTo
    {
        return $this->belongsTo(IncomingInquiry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Текущий ответственный сотрудник. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Аккаунт туриста, явно выбранный сотрудником (никогда не выводится из контактов). */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function clientLinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_linked_by');
    }

    /** Оформленная заявка; удалённая (soft delete) остаётся видимой как итог. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function draftUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_updated_by');
    }

    /**
     * Единое правило видимости (как IncomingInquiry::scopeVisibleTo): администратор видит
     * все, менеджер — только назначенные ему сейчас (чужие и ничьи не видны), остальным —
     * ничего. Для отдельной записи — InquiryPolicy::view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $query;
        }

        if ($user->hasRole(User::ROLE_MANAGER)) {
            return $query->where($query->getModel()->getTable() . '.assigned_to', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /** Обращение ещё можно вести (не оформлено и не закрыто). */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_IN_PROGRESS], true);
    }

    /** Явная проекция черновика — единственный безопасный способ отдать его наружу. */
    public function draftSnapshot(): array
    {
        return [
            'destination_country' => $this->destination_country,
            'destination_city' => $this->destination_city,
            'departure_city' => $this->departure_city,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'nights_min' => $this->nights_min,
            'nights_max' => $this->nights_max,
            'adults' => $this->adults,
            'children' => $this->children,
            'children_ages' => $this->children_ages,
            'preferences' => $this->preferences,
            'internal_notes' => $this->internal_notes,
            'revision' => $this->draft_revision,
        ];
    }

    /**
     * Инварианты согласованности (не историческое отображение статусов и не переходы —
     * переходы появятся с сервисами AOA-04/05):
     *  - origin/status из закрытых списков;
     *  - external_intake ⇔ есть incoming_inquiry_id, создателя нет;
     *    ручные origin: есть создатель, источника нет;
     *  - при создании: manager_created отвечает сам создатель, admin_created уже назначен
     *    (кем именно — решает Admin в AOA-04), оба начинаются in_progress;
     *  - new ⇒ без ответственного; in_progress ⇒ есть ответственный;
     *  - converted ⇔ есть booking_id и converted_at; closed ⇒ есть closed_at.
     *
     * Внешнее обращение при создании может иметь любой допустимый статус: перенос
     * существующих записей (I2) воспроизводит их фактическое состояние.
     */
    public function assertInvariants(): void
    {
        $fail = static function (string $message): never {
            throw new InquiryInvariantViolation($message);
        };

        if (! in_array($this->origin, self::origins(), true)) {
            $fail('Unknown inquiry origin.');
        }

        if (! in_array($this->status, self::statuses(), true)) {
            $fail('Unknown inquiry status.');
        }

        if ($this->origin === self::ORIGIN_EXTERNAL_INTAKE) {
            if ($this->incoming_inquiry_id === null) {
                $fail('An external-intake inquiry requires its IncomingInquiry source.');
            }
            if ($this->created_by !== null) {
                $fail('An external-intake inquiry has no creating employee.');
            }
        } else {
            if ($this->incoming_inquiry_id !== null) {
                $fail('A manually created inquiry cannot reference an IncomingInquiry source.');
            }
            if ($this->created_by === null) {
                $fail('A manually created inquiry requires its creator.');
            }
        }

        if (! $this->exists) {
            if ($this->origin === self::ORIGIN_MANAGER_CREATED
                && ($this->assigned_to === null || (int) $this->assigned_to !== (int) $this->created_by)) {
                $fail('A manager-created inquiry belongs to its creator.');
            }

            if ($this->origin === self::ORIGIN_ADMIN_CREATED && $this->assigned_to === null) {
                $fail('An admin-created inquiry must be assigned on creation.');
            }

            if ($this->origin !== self::ORIGIN_EXTERNAL_INTAKE && $this->status !== self::STATUS_IN_PROGRESS) {
                $fail('A manually created inquiry starts in progress.');
            }
        }

        if ($this->status === self::STATUS_NEW && $this->assigned_to !== null) {
            $fail('A new inquiry has no responsible employee yet.');
        }

        if ($this->status === self::STATUS_IN_PROGRESS && $this->assigned_to === null) {
            $fail('An inquiry in progress requires a responsible employee.');
        }

        $converted = $this->status === self::STATUS_CONVERTED;
        if ($converted !== ($this->booking_id !== null)) {
            $fail('A booking link exists exactly for a converted inquiry.');
        }

        if ($converted && $this->converted_at === null) {
            $fail('A converted inquiry requires converted_at.');
        }

        if ($this->status === self::STATUS_CLOSED && $this->closed_at === null) {
            $fail('A closed inquiry requires closed_at.');
        }
    }
}
