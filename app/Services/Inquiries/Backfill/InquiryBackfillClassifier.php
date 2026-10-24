<?php

namespace App\Services\Inquiries\Backfill;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Support\Inquiries\InquiryInvariantViolation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Единственное место, где решается, во что превращается IncomingInquiry (AOA-03-I2).
 * Его используют и перенос (dry-run/apply), и независимая сверка — поэтому они не могут
 * разойтись во мнении. Класс только читает: ни Inquiry, ни IncomingInquiry не меняются.
 *
 * Утверждённое для синтетической проверки прямое отображение статусов
 * (IncomingInquiry.workflow_state → Inquiry.status): new→new, in_progress→in_progress,
 * converted→converted, closed→closed. Это не разрешение запускать перенос на реальных данных.
 * Статус старой записи никогда не «исправляется»: если запись не проходит инварианты Inquiry,
 * это BLOCKED с кодом причины.
 *
 * Копируются только проверенные поля (см. EXPECTED_FIELDS и created_at/updated_at). НЕ
 * копируются: vendor_payload, контакты клиента, цена, отель, оператор, внешний id/провайдер
 * (происхождение остаётся в IncomingInquiry), а также параметры поездки — fly_date/nights/города
 * не отображаются надёжно на типизированный черновик Inquiry и остаются в источнике.
 * Выборка источника сама не содержит этих колонок, поэтому они даже не попадают в память.
 */
final class InquiryBackfillClassifier
{
    /** Колонки источника, которые читает перенос (без payload и контактов). */
    public const SOURCE_COLUMNS = [
        'id', 'state', 'external_type', 'workflow_state',
        'assigned_to', 'claimed_at',
        'client_user_id', 'client_linked_by', 'client_linked_at',
        'booking_id', 'converted_at', 'converted_by',
        'closed_at', 'closed_by', 'close_reason',
        'created_at', 'updated_at',
    ];

    /** Поля Inquiry, которые сравниваются с источником. */
    public const COMPARED_FIELDS = [
        'origin', 'status', 'incoming_inquiry_id', 'created_by',
        'assigned_to', 'assigned_at',
        'client_user_id', 'client_linked_by', 'client_linked_at',
        'booking_id', 'converted_at', 'converted_by',
        'closed_at', 'closed_by', 'close_reason',
    ];

    private const KNOWN_TECHNICAL_STATES = [
        IncomingInquiry::STATE_PENDING,
        IncomingInquiry::STATE_IMPORTING,
        IncomingInquiry::STATE_IMPORTED,
        IncomingInquiry::STATE_FAILED,
        IncomingInquiry::STATE_UNSUPPORTED,
    ];

    /** Поля, означающие, что с обращением уже работали сотрудники. */
    private const PROGRESS_FIELDS = [
        'assigned_to', 'claimed_at', 'client_user_id', 'client_linked_by', 'client_linked_at',
        'booking_id', 'converted_at', 'converted_by', 'closed_at', 'closed_by', 'close_reason',
    ];

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
    }

    /**
     * @param  Collection<int, IncomingInquiry>  $sources  загруженные с SOURCE_COLUMNS
     * @return array<int, InquiryBackfillDecision> ключ — id источника
     */
    public function classify(Collection $sources): array
    {
        if ($sources->isEmpty()) {
            return [];
        }

        $context = $this->loadContext($sources);
        $decisions = [];

        foreach ($sources as $source) {
            $decisions[(int) $source->id] = $this->decide($source, $context);
        }

        return $decisions;
    }

    /** @return array<string, mixed> */
    private function loadContext(Collection $sources): array
    {
        $connection = $this->qa->name;
        $sourceIds = $sources->pluck('id')->all();

        $userIds = [];
        foreach (['assigned_to', 'client_user_id', 'client_linked_by', 'converted_by', 'closed_by'] as $field) {
            foreach ($sources->pluck($field)->filter(fn ($v) => $v !== null) as $id) {
                $userIds[(int) $id] = true;
            }
        }
        $userIds = array_keys($userIds);

        $bookingIds = $sources->pluck('booking_id')->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->unique()->values()->all();
        $clientIds = $sources->pluck('client_user_id')->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->unique()->values()->all();

        $existing = Inquiry::on($connection)
            ->select(array_merge(['id'], self::COMPARED_FIELDS))
            ->whereIn('incoming_inquiry_id', $sourceIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Inquiry $i) => (int) $i->incoming_inquiry_id);

        $users = $userIds === [] ? [] : array_flip(
            User::on($connection)->whereIn('id', $userIds)->pluck('id')->map(fn ($v) => (int) $v)->all()
        );

        $tourists = $clientIds === [] ? [] : array_flip(
            User::on($connection)->whereIn('id', $clientIds)
                ->whereHas('roles', fn ($q) => $q->where('name', Role::TOURIST))
                ->pluck('id')->map(fn ($v) => (int) $v)->all()
        );

        $bookings = [];
        $bookingOwners = [];
        $bookingSourceCounts = [];

        if ($bookingIds !== []) {
            // Сырой запрос: мягко удалённая заявка (soft delete) остаётся существующей.
            $bookings = array_flip(
                DB::connection($connection)->table('bookings')->whereIn('id', $bookingIds)->pluck('id')->map(fn ($v) => (int) $v)->all()
            );

            foreach (Inquiry::on($connection)->select(['id', 'booking_id', 'incoming_inquiry_id'])->whereIn('booking_id', $bookingIds)->get() as $row) {
                $bookingOwners[(int) $row->booking_id][] = $row->incoming_inquiry_id === null ? null : (int) $row->incoming_inquiry_id;
            }

            foreach (DB::connection($connection)->table('incoming_inquiries')
                ->whereIn('booking_id', $bookingIds)
                ->groupBy('booking_id')
                ->selectRaw('booking_id, count(*) as aggregate')
                ->get() as $row) {
                $bookingSourceCounts[(int) $row->booking_id] = (int) $row->aggregate;
            }
        }

        return compact('existing', 'users', 'tourists', 'bookings', 'bookingOwners', 'bookingSourceCounts');
    }

    /** @param array<string, mixed> $ctx */
    private function decide(IncomingInquiry $source, array $ctx): InquiryBackfillDecision
    {
        $id = (int) $source->id;
        $workflow = $source->workflow_state === null ? null : (string) $source->workflow_state;
        $technical = (string) $source->state;
        /** @var Collection<int, Inquiry> $existing */
        $existing = $ctx['existing'][$id] ?? collect();

        // 1. Техническое состояние и словарь статусов: что вообще можно переносить.
        $gate = $this->technicalGate($source, $workflow, $technical);

        if ($gate !== null) {
            [$verdict, $reasons] = $gate;
            $eligible = false;

            if ($existing->isNotEmpty()) {
                return new InquiryBackfillDecision($id, InquiryBackfillDecision::CONFLICT,
                    array_merge($reasons, ['inquiry_exists_for_unmappable_source']), $eligible, $workflow, $technical, null, (int) $existing->first()->id);
            }

            return new InquiryBackfillDecision($id, $verdict, $reasons, $eligible, $workflow, $technical);
        }

        // 2. Инварианты Inquiry и разрешение ссылок — без изменения статуса источника.
        $blocked = $this->blockingReasons($source, (string) $workflow, $ctx);
        $expected = $this->expectedAttributes($source, (string) $workflow);

        if ($blocked === []) {
            try {
                (new Inquiry())->forceFill($expected)->assertInvariants();
            } catch (InquiryInvariantViolation) {
                $blocked[] = 'model_invariant';
            }
        }

        if ($blocked !== []) {
            if ($existing->isNotEmpty()) {
                return new InquiryBackfillDecision($id, InquiryBackfillDecision::CONFLICT,
                    array_merge($blocked, ['inquiry_exists_for_unmappable_source']), true, $workflow, $technical, null, (int) $existing->first()->id);
            }

            return new InquiryBackfillDecision($id, InquiryBackfillDecision::BLOCKED, $blocked, true, $workflow, $technical);
        }

        // 3. Конфликты с уже существующими данными (ничего не перезаписывается).
        $conflicts = $this->bookingConflicts($source, $ctx);
        $inquiryId = null;

        if ($existing->count() > 1) {
            $conflicts[] = 'duplicate_inquiries_for_source';
            $inquiryId = (int) $existing->first()->id;
        } elseif ($existing->count() === 1) {
            $inquiryId = (int) $existing->first()->id;
            foreach ($this->divergentFields($existing->first(), $expected) as $field) {
                $conflicts[] = 'diverges:' . $field;
            }
        }

        if ($conflicts !== []) {
            return new InquiryBackfillDecision($id, InquiryBackfillDecision::CONFLICT,
                array_values(array_unique($conflicts)), true, $workflow, $technical, $expected, $inquiryId);
        }

        $verdict = $existing->isEmpty() ? InquiryBackfillDecision::NOT_PROCESSED : InquiryBackfillDecision::MATCHED;

        return new InquiryBackfillDecision($id, $verdict, [], true, $workflow, $technical, $expected, $inquiryId);
    }

    /** @return array{0: string, 1: list<string>}|null null — источник технически пригоден */
    private function technicalGate(IncomingInquiry $source, ?string $workflow, string $technical): ?array
    {
        if ($workflow === null || ! in_array($workflow, Inquiry::statuses(), true)) {
            return [InquiryBackfillDecision::UNSUPPORTED, ['unknown_workflow_state']];
        }

        if (! in_array($technical, self::KNOWN_TECHNICAL_STATES, true)) {
            return [InquiryBackfillDecision::UNSUPPORTED, ['unknown_import_state']];
        }

        if ($technical === IncomingInquiry::STATE_IMPORTED && (int) $source->external_type === 0) {
            return null;
        }

        // Данные не загружены / тип не обрабатывается: бизнес-обращения нет. Если при этом
        // с записью уже работали, исход придумывать нельзя — это блокировка, а не пропуск.
        if ($workflow !== Inquiry::STATUS_NEW || $this->hasProgress($source)) {
            return [InquiryBackfillDecision::BLOCKED, ['business_progress_on_non_actionable_source']];
        }

        return [
            InquiryBackfillDecision::UNSUPPORTED,
            [$technical === IncomingInquiry::STATE_IMPORTED ? 'external_type_not_supported' : 'technical_state_' . $technical],
        ];
    }

    private function hasProgress(IncomingInquiry $source): bool
    {
        foreach (self::PROGRESS_FIELDS as $field) {
            if ($source->getAttribute($field) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<string>
     */
    private function blockingReasons(IncomingInquiry $source, string $workflow, array $ctx): array
    {
        $reasons = [];

        // Нарушения инвариантов Inquiry.
        if ($workflow === Inquiry::STATUS_NEW && $source->assigned_to !== null) {
            $reasons[] = 'invariant_assignee_on_new';
        }
        if ($workflow === Inquiry::STATUS_IN_PROGRESS && $source->assigned_to === null) {
            $reasons[] = 'invariant_in_progress_without_assignee';
        }
        if ($workflow === Inquiry::STATUS_CONVERTED) {
            if ($source->booking_id === null) {
                $reasons[] = 'invariant_converted_without_booking';
            }
            if ($source->converted_at === null) {
                $reasons[] = 'invariant_converted_without_timestamp';
            }
        } elseif ($source->booking_id !== null) {
            $reasons[] = 'invariant_booking_on_non_converted';
        }
        if ($workflow === Inquiry::STATUS_CLOSED && $source->closed_at === null) {
            $reasons[] = 'invariant_closed_without_timestamp';
        }

        // Поля чужого состояния: исход придумывать нельзя.
        if ($workflow !== Inquiry::STATUS_CONVERTED && ($source->converted_at !== null || $source->converted_by !== null)) {
            $reasons[] = 'conversion_fields_on_non_converted';
        }
        if ($workflow !== Inquiry::STATUS_CLOSED && ($source->closed_at !== null || $source->closed_by !== null || $source->close_reason !== null)) {
            $reasons[] = 'closure_fields_on_non_closed';
        }

        // Неразрешённые ссылки: аккаунты не создаются, источник остаётся как есть.
        $users = $ctx['users'];
        if ($source->assigned_to !== null && ! isset($users[(int) $source->assigned_to])) {
            $reasons[] = 'unresolved_assignee';
        }
        if ($source->client_user_id !== null) {
            if (! isset($users[(int) $source->client_user_id])) {
                $reasons[] = 'unresolved_client';
            } elseif (! isset($ctx['tourists'][(int) $source->client_user_id])) {
                $reasons[] = 'client_not_tourist';
            }
        }
        foreach (['client_linked_by', 'converted_by', 'closed_by'] as $field) {
            if ($source->{$field} !== null && ! isset($users[(int) $source->{$field}])) {
                $reasons[] = 'unresolved_actor_reference';
                break;
            }
        }
        if ($source->booking_id !== null && ! isset($ctx['bookings'][(int) $source->booking_id])) {
            $reasons[] = 'unresolved_booking';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<string>
     */
    private function bookingConflicts(IncomingInquiry $source, array $ctx): array
    {
        if ($source->booking_id === null) {
            return [];
        }

        $bookingId = (int) $source->booking_id;
        $id = (int) $source->id;
        $conflicts = [];

        $owners = $ctx['bookingOwners'][$bookingId] ?? [];
        $foreignOwner = array_filter($owners, fn (?int $owner) => $owner !== $id);

        if ($foreignOwner !== []) {
            // Заявка уже закреплена за другим Inquiry (ручным или от другого источника): владение сохраняется.
            $conflicts[] = 'booking_owned_by_other_inquiry';
        } elseif (($ctx['bookingSourceCounts'][$bookingId] ?? 0) > 1 && ! in_array($id, $owners, true)) {
            // Несколько источников ссылаются на одну заявку, владельца нет: победителя не выбираем.
            $conflicts[] = 'duplicate_booking_reference_in_sources';
        }

        return $conflicts;
    }

    /** @return array<string, mixed> */
    private function expectedAttributes(IncomingInquiry $source, string $workflow): array
    {
        return [
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE,
            'status' => $workflow,
            'incoming_inquiry_id' => (int) $source->id,
            'created_by' => null,
            'assigned_to' => $this->int($source->assigned_to),
            'assigned_at' => $source->claimed_at,
            'client_user_id' => $this->int($source->client_user_id),
            'client_linked_by' => $this->int($source->client_linked_by),
            'client_linked_at' => $source->client_linked_at,
            'booking_id' => $this->int($source->booking_id),
            'converted_at' => $source->converted_at,
            'converted_by' => $this->int($source->converted_by),
            'closed_at' => $source->closed_at,
            'closed_by' => $this->int($source->closed_by),
            'close_reason' => $source->close_reason,
        ];
    }

    /**
     * Поля существующего Inquiry, отличающиеся от ожидаемых (только имена, без значений).
     *
     * @param  array<string, mixed>  $expected
     * @return list<string>
     */
    private function divergentFields(Inquiry $inquiry, array $expected): array
    {
        $fields = [];

        foreach (self::COMPARED_FIELDS as $field) {
            if ($this->normalize($field, $inquiry->getAttribute($field)) !== $this->normalize($field, $expected[$field] ?? null)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function normalize(string $field, mixed $value): int|string|null
    {
        return match (true) {
            $value === null => null,
            $value instanceof CarbonInterface => $value->format('Y-m-d H:i:s'),
            str_ends_with($field, '_id') || str_ends_with($field, '_by') || $field === 'assigned_to' => (int) $value,
            default => (string) $value,
        };
    }

    private function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
