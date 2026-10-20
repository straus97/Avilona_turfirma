<?php

namespace App\Models;

use App\Support\Audit\AppendOnlyBuilder;
use App\Support\Audit\AuditEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Неизменяемое бизнес-событие (AOA-01-I1).
 *
 * Строки создаёт только App\Services\Audit\AuditRecorder. Приложение не умеет
 * изменять или удалять события: обновление и удаление через модель и через
 * построитель запросов отклоняются. Это защита уровня приложения, а не
 * гарантия БД — права/триггеры на стороне СУБД оцениваются отдельно перед E6.
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $table = 'audit_events';

    /** Массовое заполнение закрыто полностью: запись только через AuditRecorder. */
    protected $guarded = ['*'];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit events are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit events are append-only and cannot be deleted.');
        });
    }

    public function newEloquentBuilder($query): AppendOnlyBuilder
    {
        return new AppendOnlyBuilder($query);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function typeLabel(): string
    {
        return AuditEventType::labelFor($this->event_type);
    }
}
