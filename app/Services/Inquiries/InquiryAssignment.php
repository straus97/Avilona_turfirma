<?php

namespace App\Services\Inquiries;

use App\Models\AuditEvent;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Назначение и передача САМОСТОЯТЕЛЬНОГО обращения агентства другому сотруднику (AOA-04-I2).
 *
 * Только администратор (InquiryPolicy::assign). Всё выполняется в одной транзакции: блокировка
 * строки обращения, повторная проверка прав по актуальной учётной записи, допустимости обращения и
 * сотрудника, отказ при устаревшей форме, смена ответственного и событие аудита. Если запись аудита
 * не удалась, смена откатывается; отказ не оставляет события.
 *
 * Границы (намеренно):
 *  - ПРИВЯЗАННОЕ к IncomingInquiry обращение здесь не меняется никогда: ответственность за него
 *    остаётся за рабочим процессом E5 (InquiryAuthority), а второй путь назначения создал бы
 *    расхождение двух моделей. Отказ — до любой записи и без блокировки источника.
 *  - Меняется только assigned_to (+ assigned_at). created_by, origin, статус, черновик, клиент,
 *    заявка и IncomingInquiry не затрагиваются; Booking не создаётся.
 *  - Допустимый сотрудник — единое правило User::assignableToBookings() (активные менеджеры и
 *    администраторы); туристы и неактивные учётные записи отклоняются.
 *  - Обращение должно быть «в работе»: у «нового» ответственного быть не может (инвариант модели), а
 *    переходы статусов не входят в этот срез. «В работе» без ответственного возможно, когда
 *    аккаунт ответственного удалён (БД обнуляет assigned_to) — это законное первичное назначение.
 *
 * Лишние события не создаются: повтор того же сотрудника — безопасный no-op (ни записи, ни события).
 * Устаревшая форма (ответственный уже изменён) отклоняется и не перезаписывает чужое решение.
 */
class InquiryAssignment
{
    public const REASON_MIN_LENGTH = 5;
    public const REASON_MAX_LENGTH = 200;

    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_ASSIGNED = 'assigned';
    public const RESULT_REASSIGNED = 'reassigned';

    private const HISTORY_LIMIT = 50;

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  int|null  $expectedAssigneeId  ответственный, которого администратор видел на форме (null — «не назначен»)
     * @return self::RESULT_*
     *
     * @throws AuthorizationException
     * @throws InquiryAssignmentException
     */
    public function assign(Inquiry $inquiry, User $actor, int $newAssigneeId, ?int $expectedAssigneeId, string $reason): string
    {
        return DB::transaction(function () use ($inquiry, $actor, $newAssigneeId, $expectedAssigneeId, $reason): string {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

            // Актуальная учётная запись администратора: роль и активность могли измениться после входа.
            $staff = User::query()->active()->find($actor->id);

            if ($staff === null) {
                throw new AuthorizationException('Эта учётная запись не может распределять обращения.');
            }

            Gate::forUser($staff)->authorize('assign', $locked);

            if (InquiryAuthority::provenance($locked) !== InquiryAuthority::PROVENANCE_INDEPENDENT) {
                throw new InquiryAssignmentException('Ответственного за обращение, пришедшее из внешнего источника, назначают в разделе «Входящие обращения».');
            }

            if ($locked->status !== Inquiry::STATUS_IN_PROGRESS) {
                throw new InquiryAssignmentException('Обращение не находится в работе — ответственного изменить нельзя.');
            }

            $reason = $this->cleanReason($reason);

            if (User::query()->assignableToBookings()->lockForUpdate()->find($newAssigneeId) === null) {
                throw new InquiryAssignmentException('Выбранного сотрудника нельзя назначить ответственным за обращение.');
            }

            $previousId = $locked->assigned_to !== null ? (int) $locked->assigned_to : null;

            // Повтор уже выполненной передачи безвреден: ни записи, ни события.
            if ($previousId === $newAssigneeId) {
                return self::RESULT_UNCHANGED;
            }

            if ($previousId !== $expectedAssigneeId) {
                throw new InquiryAssignmentException('Ответственный за обращение уже изменён. Обновите страницу и повторите.', InquiryAssignmentException::FIELD_STALE);
            }

            try {
                $locked->forceFill([
                    'assigned_to' => $newAssigneeId,
                    'assigned_at' => now(),
                ])->save();
            } catch (InquiryInvariantViolation) {
                throw new InquiryAssignmentException('Данные обращения нарушают правила согласованности — передать его нельзя. Обратитесь к разработчикам.');
            }

            $type = $previousId === null ? AuditEventType::InquiryAssigned : AuditEventType::InquiryReassigned;

            $this->audit->record($type, $staff, InquiryDraftStore::AUDIT_SUBJECT, (int) $locked->id, [
                'previous_assignee_id' => $previousId,
                'new_assignee_id' => $newAssigneeId,
                'reason' => $reason,
            ]);

            return $previousId === null ? self::RESULT_ASSIGNED : self::RESULT_REASSIGNED;
        });
    }

    /**
     * Сотрудники, которым можно передать обращение (только id, имя и роль, без контактов).
     *
     * @return Collection<int, array{id: int, name: string, role: string}>
     */
    public function eligibleStaff(): Collection
    {
        return User::query()->assignableToBookings()->with('roles:id,name')->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'role' => $u->hasRole(User::ROLE_ADMIN) ? 'администратор' : 'менеджер',
            ]);
    }

    /**
     * Фактическая история назначений обращения для администратора — только записанные события
     * аудита; для старых назначений без события ничего не достраивается. Имена подставляются по
     * id; если учётной записи уже нет, имя не выдумывается (null).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function history(Inquiry $inquiry): Collection
    {
        $events = AuditEvent::query()
            ->where('subject_type', InquiryDraftStore::AUDIT_SUBJECT)
            ->where('subject_id', $inquiry->id)
            ->where(function ($q): void {
                $q->whereIn('event_type', [AuditEventType::InquiryAssigned->value, AuditEventType::InquiryReassigned->value])
                    // AOA-04-I3: первое назначение администратором при создании — тот же реальный факт,
                    // записанный событием создания (одно событие, не дубль назначения).
                    ->orWhere(function ($q2): void {
                        $q2->where('event_type', AuditEventType::InquiryCreated->value)
                            ->whereNotNull('metadata->initial_assignee_id');
                    });
            })
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        $ids = $events->flatMap(fn (AuditEvent $e): array => [
            $e->actor_id,
            $e->metadata['previous_assignee_id'] ?? null,
            $e->metadata['new_assignee_id'] ?? $e->metadata['initial_assignee_id'] ?? null,
        ])->filter()->unique()->values();

        $names = User::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $events->map(fn (AuditEvent $e): array => [
            'created' => $e->event_type === AuditEventType::InquiryCreated->value,
            'type' => $e->typeLabel(),
            'at' => $e->occurred_at,
            'actor' => $e->actor_id === null ? null : ($names[$e->actor_id] ?? null),
            'previous_id' => $e->metadata['previous_assignee_id'] ?? null,
            'previous' => $names[$e->metadata['previous_assignee_id'] ?? 0] ?? null,
            'new_id' => $e->metadata['new_assignee_id'] ?? $e->metadata['initial_assignee_id'] ?? null,
            'new' => $names[$e->metadata['new_assignee_id'] ?? $e->metadata['initial_assignee_id'] ?? 0] ?? null,
            'reason' => $e->metadata['reason'] ?? null,
        ]);
    }

    /**
     * Причина обязательна, сжимается по пробелам и ограничена по длине; проверяется тем же
     * валидатором метаданных аудита (e-mail, телефоны, пути, токены отклоняются) — он не ослабляется.
     */
    private function cleanReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        $length = mb_strlen($reason);

        if ($length < self::REASON_MIN_LENGTH || $length > self::REASON_MAX_LENGTH) {
            throw new InquiryAssignmentException('Причина должна содержать от ' . self::REASON_MIN_LENGTH . ' до ' . self::REASON_MAX_LENGTH . ' символов.', 'reason');
        }

        try {
            AuditMetadata::validate(['reason' => $reason]);
        } catch (InvalidArgumentException) {
            throw new InquiryAssignmentException('Причина не должна содержать email, телефоны, пути к файлам и длинные ключеподобные строки.', 'reason');
        }

        return $reason;
    }
}
