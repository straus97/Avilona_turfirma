<?php

namespace App\Services\Inquiries\Convergence;

use App\Services\Inquiries\Backfill\InquiryBackfillDecision;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryBackfillVerifier;
use App\Services\Inquiries\Backfill\InquiryReconciler;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Начальная офлайн-сходимость IncomingInquiry → Inquiry на ЗАПЕЧАТАННОМ СИНТЕТИЧЕСКОМ снимке (AOA-03-I6).
 *
 * Свой «фреймворк переноса» не вводится. Порядок: проверка идентичности → схема → «только
 * синтетические адреса» → совпадение с манифестом → стартовое окно (InquiryFreezeGate, I5) →
 * [apply: существующий InquiryBackfillService, I2] → полное сравнение (InquiryConvergenceRehearsal, I5) →
 * исторический верификатор (I2) → текущая сверка (I3/I4) → повторное сравнение с манифестом → итоговое окно.
 *
 * Правила:
 *  - любой отказ предпроверки (UNSAFE_REHEARSAL) происходит ДО первой записи; apply не вызывается;
 *  - apply пишет только недостающие Inquiry (I2): существующий Inquiry не меняется, Booking не создаётся,
 *    IncomingInquiry не трогается; различия классифицируются и остаются на решение Admin;
 *  - исключение при apply не скрывается (INTERRUPTED): уже зафиксированные Inquiry полны, повтор продолжит;
 *  - успехом считается только полностью проверенная область «пригодные источники»; технически
 *    неподдерживаемые записи учтены отдельно и миграцией НЕ называются;
 *  - CONVERGED_OFFLINE и DECISIONS_REQUIRED описывают состояние запечатанного снимка. Они не означают, что
 *    снимок содержит все обращения, принятые внешними системами, не снимают блокеры I5 (B1, B2) и никогда
 *    не разрешают cutover (production_cutover_authorized — константа false).
 */
final class OfflineConvergenceRehearsal
{
    public const UNSAFE_REHEARSAL = 'UNSAFE_REHEARSAL';
    public const INTERRUPTED = 'INTERRUPTED';
    public const NOT_CONVERGED = 'NOT_CONVERGED';
    public const DECISIONS_REQUIRED = 'DECISIONS_REQUIRED';
    public const CONVERGED_OFFLINE = 'CONVERGED_OFFLINE';

    /** Постоянные причины, по которым этот инструмент не разрешает ничего боевого. */
    public const CUTOVER_BLOCKERS = InquiryFreezeGate::CUTOVER_BLOCKERS + [
        'capture_completeness_not_established' => 'A sealed snapshot proves what the snapshot contains, not that it contains every inquiry ever accepted by the public website, mail, Tourvisor or any other writer.',
        'rehearsal_is_synthetic_only' => 'The contract refuses any address outside reserved domains; a real-data rehearsal needs a separately approved procedure.',
    ];

    private readonly OfflineSnapshotContract $contract;
    private readonly FreezeObserver $observer;

    public function __construct(private readonly VerifiedQaConnection $qa, ?FreezeObserver $observer = null, ?OfflineSnapshotContract $contract = null)
    {
        $this->contract = $contract ?? new OfflineSnapshotContract($qa);
        $this->observer = $observer ?? new OfflineIsolationObserver($qa);
    }

    /**
     * Запечатывание (шаг создателя снимка): предпроверка + манифест, без какой-либо записи.
     *
     * @return array<string, mixed>
     */
    public function seal(FreezeAttestation $attestation): array
    {
        $pre = $this->preflight(null, $attestation, false);
        $refusals = array_values(array_diff($pre['refusals'], ['snapshot_manifest_not_supplied']));

        return [
            'mode' => 'offline_seal',
            'read_only' => true,
            'connection' => ['name' => $this->qa->name, 'driver' => $this->qa->driver],
            'sealed' => $refusals === [],
            'refusals' => $refusals,
            'preflight' => $pre['summary'],
            'manifest' => $refusals === [] ? $this->contract->seal() : null,
            'production_cutover_authorized' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $manifest  манифест из seal(); без него область не определена
     * @return array<string, mixed>
     */
    public function run(?array $manifest, FreezeAttestation $attestation, bool $apply = false, int $chunkSize = InquiryBackfillService::DEFAULT_CHUNK): array
    {
        $pre = $this->preflight($manifest, $attestation, true);

        if ($pre['refusals'] !== []) {
            return $this->report(self::UNSAFE_REHEARSAL, $apply, false, $pre, null, null, $pre['refusals'], $pre['refusals']);
        }

        $start = $pre['start'];
        $countersBefore = $this->contract->protectedWriteCounters();

        $applyReport = null;
        $interrupted = null;

        if ($apply) {
            try {
                $applyReport = (new InquiryBackfillService($this->qa))->apply($chunkSize);
            } catch (Throwable $e) {
                // Только класс исключения: текст может содержать значения строк.
                $interrupted = $e::class;
            }
        }

        $convergence = (new InquiryConvergenceRehearsal($this->qa, $this->observer))->run($attestation, $chunkSize);
        $verifier = (new InquiryBackfillVerifier($this->qa))->verify($chunkSize);
        $reconciler = (new InquiryReconciler($this->qa))->reconcile($chunkSize);

        $after = $this->contract->compare($manifest);
        $end = $this->observer->observe();
        $countersAfter = $this->contract->protectedWriteCounters();

        $window = (new InquiryFreezeGate())->evaluate($start, $end, $attestation, $after['intact']);
        $this->applyProtectedCounters($window, $countersBefore, $countersAfter);

        $evidence = [
            'seal_after' => $after,
            'window' => $window,
            'convergence' => $convergence,
            'verifier' => $verifier,
            'reconciler' => $reconciler,
            'apply' => $applyReport,
            'interrupted_by' => $interrupted,
        ];

        [$status, $reasons] = $this->assess($evidence);

        return $this->report($status, $apply, $apply, $pre, $evidence, $this->accounting($manifest, $evidence), [], $reasons);
    }

    /**
     * Все проверки, которые обязаны пройти ДО первой записи. Возвращает отказы, не бросает.
     *
     * @param  array<string, mixed>|null  $manifest
     * @return array{refusals: list<string>, summary: array<string, mixed>, start: FreezeObservation|null}
     */
    private function preflight(?array $manifest, FreezeAttestation $attestation, bool $requireManifest): array
    {
        $refusals = [];
        $summary = [];

        $identity = $this->contract->identity();
        $summary['identity'] = ['ok' => $identity['ok'], 'driver' => $identity['driver'], 'database' => $identity['database'], 'port' => $identity['port'], 'version' => $identity['version'], 'problems' => $identity['problems']];
        foreach ($identity['problems'] as $problem) {
            $refusals[] = 'identity:' . $problem;
        }

        $schema = $identity['ok'] ? $this->contract->schema() : null;
        $summary['schema'] = $schema === null ? ['checked' => false] : ['ok' => $schema['ok'], 'problems' => $schema['problems']];
        foreach ($schema['problems'] ?? [] as $problem) {
            $refusals[] = 'schema:' . $problem;
        }

        // Без подтверждённых идентичности и схемы читать данные снимка нельзя.
        if ($refusals !== []) {
            return ['refusals' => $refusals, 'summary' => $summary, 'start' => null];
        }

        $synthetic = $this->contract->syntheticOnly();
        $summary['synthetic_only'] = $synthetic;
        if (! $synthetic['ok']) {
            $refusals[] = 'non_synthetic_data_present';
        }

        if ($manifest === null) {
            $summary['manifest'] = ['supplied' => false];
            if ($requireManifest) {
                $refusals[] = 'snapshot_manifest_not_supplied';
            }
        } else {
            $compare = $this->contract->compare($manifest);
            $summary['manifest'] = ['supplied' => true] + $compare;
            if (! $compare['intact']) {
                $refusals[] = 'snapshot_differs_from_manifest';
            }
        }

        $start = $this->observer->observe();
        $window = (new InquiryFreezeGate())->evaluate($start, $start, $attestation, true);
        $summary['start_window'] = ['quiescent' => $window['window_quiescent'], 'blockers' => $window['blockers'], 'unverifiable' => $window['unverifiable'], 'attestation_missing' => $window['attestation_missing']];
        if (! $window['window_quiescent']) {
            $refusals[] = 'isolation_not_proven';
        }

        return ['refusals' => $refusals, 'summary' => $summary, 'start' => $start];
    }

    /**
     * Счётчики записи защищённых таблиц (MySQL). Изменение — запись в окне; «не наблюдается» на сервере,
     * который должен их показывать, — отказ, а не «чисто». Для приватной базы в памяти счётчиков нет.
     *
     * @param  array<string, mixed>  $window
     * @param  array<string, array{insert: int, update: int, delete: int}>|null  $before
     * @param  array<string, array{insert: int, update: int, delete: int}>|null  $after
     */
    private function applyProtectedCounters(array &$window, ?array $before, ?array $after): void
    {
        if (! in_array($this->qa->driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if ($before === null || $after === null) {
            $window['unverifiable'][] = 'protected_write_counters_not_observable';
        } elseif ($before !== $after) {
            $window['blockers'][] = 'protected_table_write_observed_in_window';
        } else {
            return;
        }

        $window['window_quiescent'] = false;
    }

    /**
     * Статус и ПРИЧИНЫ статуса: «не сошлось» всегда объяснено, а не угадывается по цифрам.
     *
     * Без apply запуск — чтение: он может подтвердить сходимость, только если делать уже нечего.
     *
     * @param  array<string, mixed>  $e
     * @return array{0: string, 1: list<string>}
     */
    private function assess(array $e): array
    {
        $unsafe = [];
        if (! $e['window']['window_quiescent']) {
            $unsafe[] = 'window_not_quiescent';
        }
        if (! $e['seal_after']['intact']) {
            $unsafe[] = 'protected_set_changed';
        }
        if ($unsafe !== []) {
            return [self::UNSAFE_REHEARSAL, $unsafe];
        }

        if ($e['interrupted_by'] !== null) {
            return [self::INTERRUPTED, ['apply_interrupted']];
        }

        $sources = $e['convergence']['sources'];
        $why = [];

        if ($e['apply'] !== null && ! $e['apply']['integrity']['ok']) {
            $why[] = 'apply_integrity_failed';
        }
        if ((int) $sources['report']['new_eligible_source'] > 0) {
            $why[] = 'records_not_processed';
        }
        if (! $sources['accounting_complete']) {
            $why[] = 'accounting_incomplete';
        }
        if (! $e['reconciler']['structurally_consistent']) {
            $why[] = 'structural_anomalies';
        }
        if ($why !== []) {
            return [self::NOT_CONVERGED, $why];
        }

        $pending = [];
        if ($this->holds($sources['report']) > 0) {
            $pending[] = 'decisions_pending';
        }
        if (! $e['reconciler']['fully_reconciled']) {
            $pending[] = 'reconciler_incomplete';
        }

        return $pending === [] ? [self::CONVERGED_OFFLINE, []] : [self::DECISIONS_REQUIRED, $pending];
    }

    /** Источники, у которых есть разница, требующая решения (а не просто «не перенесено»). */
    private function holds(array $report): int
    {
        return array_sum(array_diff_key($report, array_flip(['matched_source', 'unsupported_technical_import', 'new_eligible_source'])));
    }

    /**
     * Три независимых разбиения (каждое обязано дать сумму = числу записей) и плоский перечень
     * запрошенных категорий. Источник попадает ровно в одну категорию каждого разбиения.
     *
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>
     */
    private function accounting(?array $manifest, array $e): array
    {
        $report = $e['convergence']['sources']['report'];
        $total = (int) $e['verifier']['source_total'];
        $verdicts = $e['verifier']['verdicts'];
        $reconciler = $e['reconciler'];
        $inquiriesTotal = (int) $reconciler['inquiries_total'];
        $sealedRows = (int) ($manifest['tables']['incoming_inquiries']['rows'] ?? -1);
        $preexisting = (int) ($manifest['inquiries_preexisting']['max_id'] ?? 0);

        $createdSinceSeal = (int) DB::connection($this->qa->name)->table('inquiries')
            ->where('id', '>', $preexisting)->whereNotNull('incoming_inquiry_id')->count();

        $matched = (int) ($verdicts[InquiryBackfillDecision::MATCHED] ?? 0);
        $eligible = (int) $e['verifier']['eligible'];

        $draft = $reconciler['draft_audit_evidence'];

        return [
            'source_population' => [
                'sealed_rows' => $sealedRows,
                'examined' => $total,
                'matches_seal' => $total === $sealedRows,
            ],
            'sources_by_verdict' => ['counts' => $verdicts, 'complete' => array_sum($verdicts) === $total],
            'sources_by_category' => ['counts' => $report, 'complete' => array_sum($report) === $total && $e['convergence']['sources']['accounting_complete']],
            'inquiries_by_verdict' => ['counts' => $reconciler['verdicts'], 'complete' => array_sum($reconciler['verdicts']) === $inquiriesTotal],
            'eligible_scope' => [
                'eligible' => $eligible,
                'verified_linked' => $matched,
                'not_verified' => $eligible - $matched,
                'fully_verified' => $eligible === $matched,
            ],
            'requested_categories' => [
                'source_records_examined' => $total,
                'eligible_records' => $eligible,
                'linked_consistent_total' => $matched,
                'linked_by_this_seal_lineage' => $createdSinceSeal,
                'already_linked_before_seal' => $matched - $createdSinceSeal,
                'unsupported_technical_imports' => (int) $report['unsupported_technical_import'],
                'blocked_legacy_records' => (int) ($verdicts[InquiryBackfillDecision::BLOCKED] ?? 0),
                'ownership_conflicts' => (int) $report['conflicting_current_state'] + (int) $report['assignee_missing_or_deleted'],
                'client_reference_conflicts' => (int) $report['invalid_client_reference'],
                'booking_link_conflicts' => (int) $report['duplicate_booking_linkage'],
                'missing_provenance_inquiries' => (int) ($reconciler['provenance']['malformed'] ?? 0),
                'structurally_invalid_inquiries' => (int) ($reconciler['verdicts'][InquiryReconciler::STRUCTURAL] ?? 0),
                'legitimately_changed_drafts' => (int) $reconciler['expected_evolution']['draft_edited_evidenced'],
                'unresolved_evidence' => (int) ($reconciler['verdicts'][InquiryReconciler::REQUIRES_EVIDENCE] ?? 0)
                    + (int) $draft[InquiryReconciler::DRAFT_UNEVIDENCED] + (int) $draft[InquiryReconciler::DRAFT_INCONSISTENT],
                'requires_catch_up_not_implemented' => (int) $report['requires_catch_up'],
                'unresolved_manual_decision' => (int) $report['unresolved_manual_decision'],
                'records_not_processed' => (int) $report['new_eligible_source'],
                'independent_inquiries' => (int) ($reconciler['provenance']['independent'] ?? 0),
            ],
            'decisions_required' => $this->holds($report),
        ];
    }

    /**
     * @param  array<string, mixed>  $pre
     * @param  array<string, mixed>|null  $evidence
     * @param  array<string, mixed>|null  $accounting
     * @param  list<string>  $refusals
     * @param  list<string>  $statusReasons
     * @return array<string, mixed>
     */
    private function report(string $status, bool $applyRequested, bool $applyAttempted, array $pre, ?array $evidence, ?array $accounting, array $refusals, array $statusReasons): array
    {
        return [
            'mode' => 'offline_convergence_rehearsal',
            'connection' => ['name' => $this->qa->name, 'driver' => $this->qa->driver],
            'status' => $status,
            'status_reasons' => $statusReasons,
            'provisional' => $status === self::UNSAFE_REHEARSAL,
            'apply_requested' => $applyRequested,
            // Предпроверка отказала → записи не было вообще.
            'apply_attempted' => $refusals === [] && $applyAttempted,
            'production_cutover_authorized' => false,
            'completeness_of_capture' => 'NOT_ESTABLISHED_BY_THIS_TOOL',
            'refusals' => $refusals,
            'preflight' => $pre['summary'],
            'seal_after' => $evidence['seal_after'] ?? null,
            'window' => $evidence === null ? null : [
                'quiescent' => $evidence['window']['window_quiescent'],
                'blockers' => $evidence['window']['blockers'],
                'unverifiable' => $evidence['window']['unverifiable'],
                'attested' => $evidence['window']['attested'],
                'attestation_note' => $evidence['window']['attestation_note'],
            ],
            'apply' => $evidence === null ? null : [
                'interrupted_by' => $evidence['interrupted_by'],
                'outcomes' => $evidence['apply']['outcomes'] ?? null,
                'inquiries_created' => $evidence['apply']['inquiries_created'] ?? null,
                'race_resolved' => $evidence['apply']['race_resolved'] ?? null,
                'integrity' => $evidence['apply']['integrity'] ?? null,
            ],
            'verification' => $evidence === null ? null : [
                'historical_i2' => ['fully_verified' => $evidence['verifier']['fully_verified'], 'global' => $evidence['verifier']['global']],
                'current_state_i3_i4' => [
                    'structurally_consistent' => $evidence['reconciler']['structurally_consistent'],
                    'fully_reconciled' => $evidence['reconciler']['fully_reconciled'],
                    'incomplete_because' => $evidence['reconciler']['incomplete_because'],
                    'provenance' => $evidence['reconciler']['provenance'],
                    'reasons' => $evidence['reconciler']['reasons'],
                ],
                'full_comparison_i5' => [
                    'status' => $evidence['convergence']['status'],
                    'planner_categories' => $evidence['convergence']['sources']['planner_categories'],
                    'proposed_actions' => $evidence['convergence']['sources']['proposed_actions'],
                    'reasons' => $evidence['convergence']['sources']['reasons'],
                    'sample_source_ids' => $evidence['convergence']['sources']['sample_source_ids'],
                ],
            ],
            'accounting' => $accounting,
            'cutover_blockers' => self::CUTOVER_BLOCKERS,
            'b1_b2' => ['B1' => 'OPEN', 'B2' => 'OPEN'],
        ];
    }
}
