<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Единственная точка записи бизнес-аудита (AOA-01-I1).
 *
 * Сервис НЕ открывает собственную транзакцию: вызывается внутри DB::transaction
 * бизнес-операции, поэтому изменение данных и событие аудита фиксируются или
 * откатываются вместе. Сам по себе I1 не подключён ни к одному процессу.
 */
class AuditRecorder
{
    /**
     * @param  User|int|null  $actor  null — системное событие без пользователя
     * @param  array<string, mixed>  $metadata  короткие скаляры, см. AuditMetadata
     */
    public function record(
        AuditEventType $type,
        User|int|null $actor,
        string $subjectType,
        ?int $subjectId,
        array $metadata = [],
    ): AuditEvent {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $subjectType) !== 1) {
            throw new InvalidArgumentException('Audit subject type must be a snake_case domain name.');
        }

        if ($subjectId !== null && $subjectId < 1) {
            throw new InvalidArgumentException('Audit subject id must be positive.');
        }

        $actorId = $actor instanceof User ? (int) $actor->getKey() : $actor;

        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException('Audit actor id must be positive.');
        }

        $clean = AuditMetadata::validate($metadata);

        $event = new AuditEvent();
        $event->forceFill([
            'uuid' => (string) Str::uuid(),
            'event_type' => $type->value,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $clean === [] ? null : $clean,
            'occurred_at' => now(),
        ])->save();

        return $event;
    }
}
