<?php

namespace App\Services\Bookings;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Назначение и переназначение ответственного по заявке (AOA-01-I2B, G7).
 *
 * Только администратор (BookingPolicy::assignManager). Всё выполняется в одной
 * транзакции: блокировка строки заявки, проверка прав и допустимости сотрудника
 * по актуальным данным, отказ при устаревшей форме, смена ответственного и
 * событие аудита. Если запись аудита не удалась, смена откатывается.
 * Статус, клиент, оплата, сообщения и документы заявки не затрагиваются
 * (первичное назначение, как и раньше, переводит NEW → PROGRESS силами модели).
 */
class BookingAssignment
{
    public const AUDIT_SUBJECT = 'booking';

    public const REASON_NOTE_MIN_LENGTH = 5;
    public const REASON_NOTE_MAX_LENGTH = 200;

    /** Код причины → подпись. Причина обязательна при СМЕНЕ существующего ответственного. */
    public const REASONS = [
        'workload' => 'Распределение нагрузки',
        'absence' => 'Отсутствие сотрудника (отпуск, болезнь)',
        'specialization' => 'Специализация по направлению',
        'client_request' => 'Просьба клиента',
        'staff_change' => 'Смена состава сотрудников',
        'other' => 'Другая причина (описать)',
    ];

    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_ASSIGNED = 'assigned';
    public const RESULT_REASSIGNED = 'reassigned';

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  int|null  $expectedManagerId  ответственный, которого администратор видел на форме
     * @return self::RESULT_*
     */
    public function assign(
        Booking $booking,
        User $actor,
        int $newManagerId,
        ?int $expectedManagerId,
        ?string $reasonCode,
        ?string $reasonNote,
    ): string {
        return DB::transaction(function () use ($booking, $actor, $newManagerId, $expectedManagerId, $reasonCode, $reasonNote): string {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            Gate::forUser($actor)->authorize('assignManager', $locked);

            if (User::query()->assignableToBookings()->lockForUpdate()->find($newManagerId) === null) {
                throw new BookingAssignmentException('Выбранного сотрудника нельзя назначить ответственным по заявке.');
            }

            $previousId = $locked->manager_id !== null ? (int) $locked->manager_id : null;

            // Повтор уже выполненного назначения безвреден: ни записи, ни события.
            if ($previousId === $newManagerId && $locked->status !== Booking::STATUS_NEW) {
                return self::RESULT_UNCHANGED;
            }

            if ($previousId !== $expectedManagerId) {
                throw new BookingAssignmentException('Ответственный по заявке уже изменён. Обновите страницу и повторите.');
            }

            // Смена существующего ответственного — «переназначение» и требует причины.
            // Назначение при отсутствии ответственного (или подтверждение того же сотрудника
            // на новой заявке) — «назначение», причина не нужна.
            $isChange = $previousId !== null && $previousId !== $newManagerId;

            $metadata = [
                'previous_manager_id' => $previousId,
                'new_manager_id' => $newManagerId,
            ];

            if ($isChange) {
                $metadata += $this->cleanReason($reasonCode, $reasonNote);
            }

            if ($locked->status === Booking::STATUS_NEW) {
                $locked->assignManager($newManagerId);
            } else {
                $locked->reassignManager($newManagerId);
            }

            $type = $isChange ? AuditEventType::BookingReassigned : AuditEventType::BookingAssigned;
            $result = $isChange ? self::RESULT_REASSIGNED : self::RESULT_ASSIGNED;

            $this->audit->record($type, $actor, self::AUDIT_SUBJECT, (int) $locked->id, $metadata);

            return $result;
        });
    }

    /**
     * История ответственных по заявке для администратора: события аудита этой заявки.
     * Имена подставляются по id, причина выводится только экранированной.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function history(Booking $booking): \Illuminate\Support\Collection
    {
        $events = AuditEvent::query()
            ->where('subject_type', self::AUDIT_SUBJECT)
            ->where('subject_id', $booking->id)
            ->whereIn('event_type', [AuditEventType::BookingAssigned->value, AuditEventType::BookingReassigned->value])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $ids = $events->flatMap(fn (AuditEvent $e): array => [
            $e->actor_id,
            $e->metadata['previous_manager_id'] ?? null,
            $e->metadata['new_manager_id'] ?? null,
        ])->filter()->unique()->values();

        $names = User::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $events->map(fn (AuditEvent $e): array => [
            'type' => $e->typeLabel(),
            'at' => $e->occurred_at,
            'actor' => $names[$e->actor_id] ?? null,
            'previous' => $names[$e->metadata['previous_manager_id'] ?? 0] ?? null,
            'new' => $names[$e->metadata['new_manager_id'] ?? 0] ?? null,
            'reason' => isset($e->metadata['reason_code']) ? (self::REASONS[$e->metadata['reason_code']] ?? null) : null,
            'note' => $e->metadata['reason'] ?? null,
        ]);
    }

    /**
     * Причина: код из справочника + пояснение. Для «other» пояснение обязательно,
     * иначе необязательно. Пояснение проверяется валидатором метаданных аудита
     * (e-mail, телефоны, пути, токены отклоняются) — валидатор не ослабляется.
     *
     * @return array{reason_code: string, reason?: string}
     */
    private function cleanReason(?string $code, ?string $note): array
    {
        if ($code === null || ! array_key_exists($code, self::REASONS)) {
            throw new BookingAssignmentException('Выберите причину смены ответственного.', 'reason_code');
        }

        $note = trim((string) preg_replace('/\s+/u', ' ', (string) $note));
        $length = mb_strlen($note);

        if ($note === '') {
            if ($code === 'other') {
                throw new BookingAssignmentException('Опишите причину: от ' . self::REASON_NOTE_MIN_LENGTH . ' до ' . self::REASON_NOTE_MAX_LENGTH . ' символов.', 'reason');
            }

            return ['reason_code' => $code];
        }

        if ($length < self::REASON_NOTE_MIN_LENGTH || $length > self::REASON_NOTE_MAX_LENGTH) {
            throw new BookingAssignmentException('Пояснение должно содержать от ' . self::REASON_NOTE_MIN_LENGTH . ' до ' . self::REASON_NOTE_MAX_LENGTH . ' символов.', 'reason');
        }

        try {
            AuditMetadata::validate(['reason' => $note]);
        } catch (InvalidArgumentException) {
            throw new BookingAssignmentException('Пояснение не должно содержать email, телефоны, пути к файлам и длинные ключеподобные строки.', 'reason');
        }

        return ['reason_code' => $code, 'reason' => $note];
    }
}
