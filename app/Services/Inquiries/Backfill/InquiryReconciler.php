<?php

namespace App\Services\Inquiries\Backfill;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Services\Inquiries\InquiryAuthority;
use App\Services\Inquiries\InquiryDraftStore;
use App\Support\Audit\AuditEventType;
use App\Support\Inquiries\InquiryInvariantViolation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Переходная (runtime) сверка IncomingInquiry ↔ Inquiry (AOA-03-I3). Только чтение: ни одна
 * таблица не меняется — не переписываются статусы, ответственные, черновики, ссылки на Booking,
 * новые Inquiry не создаются. Работает через VerifiedQaConnection, как и остальные инструменты
 * переноса: на каноническую базу он в I3 направить нельзя (это отдельное решение владельца).
 *
 * Два разных контракта сверки (не смешивать):
 *
 *  A. НАЧАЛЬНАЯ целостность переноса — InquiryBackfillVerifier (I2, не менялся): «источник
 *     представлен точно так, как перенос его создал». Верна только до первого законного
 *     изменения Inquiry или источника.
 *  B. ЭТА сверка — «две модели по-прежнему структурно согласованы», когда обе живут. Изменчивые
 *     бизнес-поля не обязаны совпадать со снимком навсегда, но и не игнорируются.
 *
 * Классификация (каждое отличие попадает ровно в одну категорию):
 *  - STRUCTURAL — подлинная порча или нарушение связей: нет источника, некорректное
 *    происхождение, дубль связей, конфликт ссылок на Booking, нарушенный инвариант Inquiry,
 *    Inquiry у неподдерживаемого источника. Не объяснимо развитием бизнеса.
 *  - OWNERSHIP_CONFLICT — у пары разные текущие ответственные. Победитель не выбирается, доступ
 *    через Inquiry-сторону закрыт (InquiryAuthority), автоматического исправления нет.
 *  - REQUIRES_EVIDENCE — различаются статус/закрытие/конверсия/клиент/время назначения. Без
 *    журнала изменений Inquiry или события синхронизации нельзя отличить «источник ушёл вперёд
 *    после переноса» от «законной правки Inquiry» от ручной порчи; вердикт не угадывается.
 *    Фактическое направление (по стадии процесса) приводится как справка, не как причина.
 *  - IN_SYNC — все сравниваемые поля совпадают. Правки черновика Inquiry сюда не относятся: поля
 *    черновика в источнике отсутствуют, это ожидаемая эволюция (считается отдельно).
 *  - Вне пар: UNPROCESSED — пригодный источник без Inquiry (незавершённый перенос ИЛИ
 *    поступивший после него; отличить без отметки запуска переноса нельзя).
 *
 * Правка черновика считается законной эволюцией ТОЛЬКО при доказательстве (AOA-03-I4): версия
 * черновика N > 0 должна быть подтверждена событиями аудита `inquiry.draft_updated` с версиями
 * ровно 1…N по цепочке «до → после» и актором последнего события = draft_updated_by. Версия без
 * событий, с пропусками или событие без соответствующей версии — это «не доказано», а не порча и
 * не эволюция: отчёт становится неполным, но вердикт пары не меняется. Событий для назначения,
 * статуса, клиента, закрытия и конверсии на стороне Inquiry нет — ни один сервис сегодня их не
 * меняет, — поэтому REQUIRES_EVIDENCE и UNPROCESSED этим не разрешаются.
 */
final class InquiryReconciler
{
    public const IN_SYNC = 'IN_SYNC';
    public const STRUCTURAL = 'STRUCTURAL';
    public const OWNERSHIP_CONFLICT = 'OWNERSHIP_CONFLICT';
    public const REQUIRES_EVIDENCE = 'REQUIRES_EVIDENCE';
    public const INDEPENDENT = 'INDEPENDENT';

    public const SAMPLE_LIMIT = 20;

    /** Версия черновика полностью подтверждена событиями аудита. */
    public const DRAFT_EVIDENCED = 'evidenced';
    /** Версия черновика > 0, а событий аудита нет вовсе. */
    public const DRAFT_UNEVIDENCED = 'unevidenced';
    /** События есть, но не складываются в версию (пропуск, лишнее событие, чужой актор, версия 0 при событиях). */
    public const DRAFT_INCONSISTENT = 'inconsistent';

    /** Изменчивые поля, сравниваемые с источником (имя Inquiry => имя в IncomingInquiry). */
    private const MIRRORED_FIELDS = [
        'status' => 'workflow_state',
        'assigned_at' => 'claimed_at',
        'client_user_id' => 'client_user_id',
        'client_linked_by' => 'client_linked_by',
        'client_linked_at' => 'client_linked_at',
        'booking_id' => 'booking_id',
        'converted_at' => 'converted_at',
        'converted_by' => 'converted_by',
        'closed_at' => 'closed_at',
        'closed_by' => 'closed_by',
        'close_reason' => 'close_reason',
    ];

    private const INQUIRY_COLUMNS = [
        'id', 'origin', 'status', 'incoming_inquiry_id', 'created_by',
        'assigned_to', 'assigned_at',
        'client_user_id', 'client_linked_by', 'client_linked_at',
        'booking_id', 'converted_at', 'converted_by',
        'closed_at', 'closed_by', 'close_reason', 'draft_revision', 'draft_updated_by',
    ];

    /** Чего эта сверка принципиально не знает (выводится в отчёте, не скрывается). */
    private const LIMITS = [
        'no_inquiry_change_log' => 'Only trip-draft edits are audited (inquiry.draft_updated, AOA-03-I4). Inquiry-side status, assignment, client, closure and conversion changes have no audit trail (no service changes them yet, AOA-04/05), so an Inquiry/source difference in those fields cannot be attributed to either side.',
        'no_sync_marker' => 'No backfill/sync high-water mark exists, so an unprocessed source cannot be classified as newly arrived or missed.',
        'draft_not_mirrored' => 'Trip draft fields have no source counterpart and are never compared.',
    ];

    private int $total = 0;
    /** @var array<string, int> */
    private array $provenance = [];
    /** @var array<string, int> */
    private array $verdicts = [];
    /** @var array<string, int> */
    private array $reasons = [];
    /** @var array<string, list<int>> */
    private array $samples = [];
    /** @var array<string, int> */
    private array $direction = [];
    private int $draftEdited = 0;
    private int $draftEditedEvidenced = 0;
    /** @var array<string, int> */
    private array $draftEvidence = [];

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
    }

    /** @return array<string, mixed> */
    public function reconcile(int $chunkSize = InquiryBackfillService::DEFAULT_CHUNK): array
    {
        $this->total = 0;
        $this->provenance = $this->verdicts = $this->reasons = $this->samples = $this->direction = [];
        $this->draftEdited = 0;
        $this->draftEditedEvidenced = 0;
        $this->draftEvidence = [self::DRAFT_EVIDENCED => 0, self::DRAFT_UNEVIDENCED => 0, self::DRAFT_INCONSISTENT => 0];

        Inquiry::on($this->qa->name)
            ->select(self::INQUIRY_COLUMNS)
            ->chunkById($chunkSize, fn (Collection $chunk) => $this->reconcileChunk($chunk));

        $unprocessed = $this->unprocessedSources();
        $global = $this->globalChecks();

        $structural = $this->verdicts[self::STRUCTURAL] ?? 0;
        $anomalies = $global['duplicate_source_links'] + $global['duplicate_booking_links'];

        $incomplete = [];
        foreach ([
            'structural_anomalies' => $structural + $anomalies,
            'ownership_conflicts' => $this->verdicts[self::OWNERSHIP_CONFLICT] ?? 0,
            'requires_evidence' => $this->verdicts[self::REQUIRES_EVIDENCE] ?? 0,
            'unprocessed_sources' => $unprocessed['total'],
            'draft_without_audit_evidence' => $this->draftEvidence[self::DRAFT_UNEVIDENCED] + $this->draftEvidence[self::DRAFT_INCONSISTENT],
        ] as $name => $count) {
            if ($count > 0) {
                $incomplete[$name] = $count;
            }
        }

        ksort($this->provenance);
        ksort($this->verdicts);
        ksort($this->reasons);
        ksort($this->samples);
        ksort($this->direction);

        return [
            'mode' => 'reconcile',
            'connection' => ['name' => $this->qa->name, 'driver' => $this->qa->driver],
            'inquiries_total' => $this->total,
            'provenance' => $this->provenance,
            'verdicts' => $this->verdicts,
            'reasons' => $this->reasons,
            'sample_inquiry_ids' => $this->samples,
            'direction_of_difference' => $this->direction,
            'expected_evolution' => ['draft_edited' => $this->draftEdited, 'draft_edited_evidenced' => $this->draftEditedEvidenced],
            'draft_audit_evidence' => $this->draftEvidence,
            'unprocessed_sources' => $unprocessed,
            'global' => $global,
            // Структурно согласованы: нет порчи связей. Это условие допускает законную эволюцию.
            'structurally_consistent' => $structural === 0 && $anomalies === 0,
            // Полностью сверено: ничего не требует решения, доказательств или переноса.
            'fully_reconciled' => $incomplete === [],
            'incomplete_because' => $incomplete,
            'limits' => self::LIMITS,
        ];
    }

    /** @param Collection<int, Inquiry> $chunk */
    private function reconcileChunk(Collection $chunk): void
    {
        $sourceIds = $chunk->pluck('incoming_inquiry_id')->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->unique()->values()->all();

        $sources = $sourceIds === [] ? collect() : IncomingInquiry::on($this->qa->name)
            ->select(InquiryBackfillClassifier::SOURCE_COLUMNS)
            ->whereIn('id', $sourceIds)
            ->get()
            ->keyBy(fn (IncomingInquiry $s) => (int) $s->id);

        $bookingUsers = $this->bookingIdentities($chunk, $sources);
        $draftEvents = $this->draftEvents($chunk);

        foreach ($chunk as $inquiry) {
            /** @var Inquiry $inquiry */
            $this->total++;
            $provenance = InquiryAuthority::provenance($inquiry);
            $this->provenance[$provenance] = ($this->provenance[$provenance] ?? 0) + 1;

            if ($inquiry->draft_revision > 0) {
                $this->draftEdited++;
            }

            $events = $draftEvents[(int) $inquiry->id] ?? [];
            $draftEvidence = $this->draftEvidenceOf($inquiry, $events);
            $this->draftEvidence[$draftEvidence]++;
            if ($inquiry->draft_revision > 0 && $draftEvidence === self::DRAFT_EVIDENCED) {
                $this->draftEditedEvidenced++;
            }

            $reasons = $this->structuralReasons($inquiry, $provenance, $sources, $bookingUsers);
            $verdict = $reasons !== [] ? self::STRUCTURAL : null;

            if ($verdict === null && $provenance === InquiryAuthority::PROVENANCE_INDEPENDENT) {
                $verdict = self::INDEPENDENT;
            }

            if ($verdict === null) {
                /** @var IncomingInquiry $source */
                $source = $sources->get((int) $inquiry->incoming_inquiry_id);
                $ownership = $this->normalize($inquiry->assigned_to) !== $this->normalize($source->assigned_to);
                $diverged = $this->divergedFields($inquiry, $source);

                if ($ownership) {
                    $reasons[] = 'assignee_differs';
                }
                foreach ($diverged as $field) {
                    $reasons[] = 'diverges:' . $field;
                }

                if ($ownership || $diverged !== []) {
                    $direction = $this->direction($inquiry, $source);
                    $this->direction[$direction] = ($this->direction[$direction] ?? 0) + 1;
                }

                $verdict = match (true) {
                    $ownership => self::OWNERSHIP_CONFLICT,
                    $diverged !== [] => self::REQUIRES_EVIDENCE,
                    default => self::IN_SYNC,
                };
            }

            $this->verdicts[$verdict] = ($this->verdicts[$verdict] ?? 0) + 1;

            // «Не доказано» — справка об аудите черновика; в причины вердикта пары она не входит.
            if ($draftEvidence !== self::DRAFT_EVIDENCED) {
                $reasons[] = 'draft_' . $draftEvidence;
            }

            foreach ($reasons as $reason) {
                $this->reasons[$reason] = ($this->reasons[$reason] ?? 0) + 1;
                $this->samples[$reason] ??= [];
                if (count($this->samples[$reason]) < self::SAMPLE_LIMIT) {
                    $this->samples[$reason][] = (int) $inquiry->id;
                }
            }
        }
    }

    /**
     * События аудита правки черновика для обращений порции: id обращения → события в порядке
     * записи. Только числа: значений черновика в журнале нет по построению (InquiryDraftStore).
     *
     * @param  Collection<int, Inquiry>  $chunk
     * @return array<int, list<array{previous: ?int, new: ?int, actor: ?int}>>
     */
    private function draftEvents(Collection $chunk): array
    {
        $events = [];

        $rows = DB::connection($this->qa->name)->table('audit_events')
            ->where('event_type', AuditEventType::InquiryDraftUpdated->value)
            ->where('subject_type', InquiryDraftStore::AUDIT_SUBJECT)
            ->whereIn('subject_id', $chunk->pluck('id')->map(fn ($v) => (int) $v)->all())
            ->orderBy('id')
            ->get(['subject_id', 'actor_id', 'metadata']);

        foreach ($rows as $row) {
            $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : null;
            $metadata = is_array($metadata) ? $metadata : [];

            $events[(int) $row->subject_id][] = [
                'previous' => is_int($metadata['previous_revision'] ?? null) ? $metadata['previous_revision'] : null,
                'new' => is_int($metadata['new_revision'] ?? null) ? $metadata['new_revision'] : null,
                'actor' => $row->actor_id === null ? null : (int) $row->actor_id,
            ];
        }

        return $events;
    }

    /**
     * Доказана ли версия черновика: события образуют цепочку 0→1→…→N без пропусков и повторов,
     * а актор последнего события — тот, кто записан в draft_updated_by (если он не удалён).
     *
     * @param  list<array{previous: ?int, new: ?int, actor: ?int}>  $events
     */
    private function draftEvidenceOf(Inquiry $inquiry, array $events): string
    {
        $revision = (int) $inquiry->draft_revision;

        if ($revision === 0) {
            return $events === [] ? self::DRAFT_EVIDENCED : self::DRAFT_INCONSISTENT;
        }

        if ($events === []) {
            return self::DRAFT_UNEVIDENCED;
        }

        if (count($events) !== $revision) {
            return self::DRAFT_INCONSISTENT;
        }

        foreach ($events as $index => $event) {
            if ($event['previous'] !== $index || $event['new'] !== $index + 1) {
                return self::DRAFT_INCONSISTENT;
            }
        }

        if ($inquiry->draft_updated_by !== null && $events[$revision - 1]['actor'] !== (int) $inquiry->draft_updated_by) {
            return self::DRAFT_INCONSISTENT;
        }

        return self::DRAFT_EVIDENCED;
    }

    /**
     * Структурные нарушения одной записи. Развитием бизнеса не объясняются.
     *
     * @param  Collection<int, IncomingInquiry>  $sources
     * @param  array<int, array<string, true>>  $bookingUsers  заявка → участники, ссылающиеся на неё
     * @return list<string>
     */
    private function structuralReasons(Inquiry $inquiry, string $provenance, Collection $sources, array $bookingUsers): array
    {
        $reasons = [];

        if ($provenance === InquiryAuthority::PROVENANCE_MALFORMED) {
            $reasons[] = $inquiry->origin === Inquiry::ORIGIN_EXTERNAL_INTAKE ? 'external_without_source_reference' : 'malformed_provenance';
        }

        $source = null;
        if ($provenance === InquiryAuthority::PROVENANCE_LINKED) {
            $source = $sources->get((int) $inquiry->incoming_inquiry_id);

            if ($source === null) {
                $reasons[] = 'missing_source';
            } elseif ($source->state !== IncomingInquiry::STATE_IMPORTED || (int) $source->external_type !== 0) {
                $reasons[] = 'inquiry_for_unsupported_source';
            }
        }

        try {
            $inquiry->assertInvariants();
        } catch (InquiryInvariantViolation) {
            $reasons[] = 'inquiry_invariant_violation';
        }

        if ($source !== null && $inquiry->booking_id !== null && $source->booking_id !== null
            && (int) $inquiry->booking_id !== (int) $source->booking_id) {
            $reasons[] = 'booking_reference_conflict';
        }

        foreach (array_filter([$inquiry->booking_id, $source?->booking_id]) as $bookingId) {
            if (count($bookingUsers[(int) $bookingId] ?? []) > 1) {
                $reasons[] = 'booking_shared_between_records';
                break;
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Для заявок, упомянутых в порции, — множество «бизнес-обращений», которые на них ссылаются.
     * Пара (источник + его Inquiry) — одно обращение; чужой источник или самостоятельный Inquiry
     * на ту же заявку — второе.
     *
     * @param  Collection<int, Inquiry>  $chunk
     * @param  Collection<int, IncomingInquiry>  $sources
     * @return array<int, array<string, true>>
     */
    private function bookingIdentities(Collection $chunk, Collection $sources): array
    {
        $ids = [];
        foreach ($chunk as $inquiry) {
            if ($inquiry->booking_id !== null) {
                $ids[(int) $inquiry->booking_id] = true;
            }
        }
        foreach ($sources as $source) {
            if ($source->booking_id !== null) {
                $ids[(int) $source->booking_id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $ids = array_keys($ids);
        $users = [];

        foreach (DB::connection($this->qa->name)->table('incoming_inquiries')->whereIn('booking_id', $ids)->get(['id', 'booking_id']) as $row) {
            $users[(int) $row->booking_id]['s' . $row->id] = true;
        }

        foreach (DB::connection($this->qa->name)->table('inquiries')->whereIn('booking_id', $ids)->get(['id', 'booking_id', 'incoming_inquiry_id']) as $row) {
            $identity = $row->incoming_inquiry_id !== null ? 's' . $row->incoming_inquiry_id : 'i' . $row->id;
            $users[(int) $row->booking_id][$identity] = true;
        }

        return $users;
    }

    /** @return list<string> */
    private function divergedFields(Inquiry $inquiry, IncomingInquiry $source): array
    {
        $fields = [];

        foreach (self::MIRRORED_FIELDS as $inquiryField => $sourceField) {
            if ($this->normalize($inquiry->getAttribute($inquiryField)) !== $this->normalize($source->getAttribute($sourceField))) {
                $fields[] = $inquiryField;
            }
        }

        return $fields;
    }

    /**
     * Фактическое направление различия по стадии процесса (new < in_progress < converted/closed).
     * Справка для человека, не причина и не вердикт.
     */
    private function direction(Inquiry $inquiry, IncomingInquiry $source): string
    {
        $inquiryStage = $this->stage((string) $inquiry->status);
        $sourceStage = $this->stage((string) $source->workflow_state);

        return match (true) {
            $sourceStage > $inquiryStage => 'source_ahead',
            $sourceStage < $inquiryStage => 'inquiry_ahead',
            default => 'same_stage',
        };
    }

    private function stage(string $status): int
    {
        return match ($status) {
            Inquiry::STATUS_NEW => 0,
            Inquiry::STATUS_IN_PROGRESS => 1,
            default => 2,
        };
    }

    /**
     * Пригодные источники без Inquiry: незавершённый перенос либо поступление после него.
     *
     * @return array{total: int, by_workflow_state: array<string, int>, sample_source_ids: list<int>}
     */
    private function unprocessedSources(): array
    {
        $unprocessed = fn () => IncomingInquiry::on($this->qa->name)
            ->where('state', IncomingInquiry::STATE_IMPORTED)
            ->where('external_type', 0)
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('inquiries')->whereColumn('inquiries.incoming_inquiry_id', 'incoming_inquiries.id'));

        $byState = $unprocessed()
            ->groupBy('workflow_state')
            ->selectRaw('workflow_state, count(*) as aggregate')
            ->pluck('aggregate', 'workflow_state')
            ->map(fn ($n) => (int) $n)
            ->all();
        ksort($byState);

        return [
            'total' => array_sum($byState),
            'by_workflow_state' => $byState,
            'sample_source_ids' => $unprocessed()->orderBy('id')->limit(self::SAMPLE_LIMIT)->pluck('id')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** @return array<string, int> */
    private function globalChecks(): array
    {
        $db = DB::connection($this->qa->name);
        $duplicates = fn (string $column): int => $db->table('inquiries')
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('count(*) > 1')
            ->select($column)
            ->get()
            ->count();

        return [
            'duplicate_source_links' => $duplicates('incoming_inquiry_id'),
            'duplicate_booking_links' => $duplicates('booking_id'),
            'sources_total' => (int) $db->table('incoming_inquiries')->count(),
        ];
    }

    private function normalize(mixed $value): int|string|null
    {
        return match (true) {
            $value === null => null,
            $value instanceof CarbonInterface => $value->format('Y-m-d H:i:s'),
            is_int($value) => $value,
            is_string($value) && ctype_digit($value) => (int) $value,
            default => (string) $value,
        };
    }
}
