<?php

namespace App\Services\Inquiries\Convergence;

use App\Models\Inquiry;
use App\Services\Inquiries\Backfill\InquiryBackfillClassifier;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryBackfillSnapshot;
use App\Services\Inquiries\Backfill\InquiryCatchUpPlanner;
use App\Services\Inquiries\Backfill\InquiryReconciler;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Models\IncomingInquiry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Полное сравнение «каждый источник против Inquiry» внутри наблюдаемого окна (AOA-03-I5).
 * ТОЛЬКО ЧТЕНИЕ, только на проверенной QA-базе (VerifiedQaConnection): ничего не создаёт, не
 * зеркалирует, не меняет Booking, не пишет аудит. Использует существующие классификатор (I2),
 * планировщик (I4), сверку (I3/I4) и снимок (I2); своего «фреймворка миграции» не вводит.
 *
 * Порядок: замер → отпечатки → полный проход → сверка → отпечатки → замер → оценка окна.
 * Если окно не доказано или источник изменился во время прохода, счётчики остаются, но результат
 * помечен provisional и статус — UNSAFE_FREEZE: «успешной» сходимости для такого запуска нет.
 * Статус CONVERGED_IN_WINDOW означает «в этом окне нет ни одного расхождения и ничего не требует
 * решения»; он не разрешает cutover (production_cutover_authorized всегда false).
 *
 * Каждый источник попадает ровно в одну категорию отчёта; сумма категорий обязана равняться числу
 * источников, иначе `accounting_complete = false` и сходимости нет.
 */
final class InquiryConvergenceRehearsal
{
    public const UNSAFE_FREEZE = 'UNSAFE_FREEZE';
    public const NOT_CONVERGED = 'NOT_CONVERGED';
    public const CONVERGED_IN_WINDOW = 'CONVERGED_IN_WINDOW';

    public const SAMPLE_LIMIT = 20;

    /** Категория отчёта → категории планировщика, которые в неё входят. */
    private const REPORT_CATEGORIES = [
        'matched_source' => [InquiryCatchUpPlanner::UNCHANGED_LINKED],
        'new_eligible_source' => [InquiryCatchUpPlanner::NEW_ELIGIBLE_SOURCE],
        'requires_catch_up' => [InquiryCatchUpPlanner::POST_BACKFILL_CONVERSION, InquiryCatchUpPlanner::CLOSURE, InquiryCatchUpPlanner::CLIENT_LINK_CHANGE],
        'conflicting_current_state' => [InquiryCatchUpPlanner::REASSIGNMENT, InquiryCatchUpPlanner::INQUIRY_AHEAD],
        'assignee_missing_or_deleted' => [],
        'invalid_client_reference' => [InquiryCatchUpPlanner::UNRESOLVED_CLIENT_REFERENCE],
        'duplicate_booking_linkage' => [InquiryCatchUpPlanner::DUPLICATE_BOOKING],
        'unsupported_technical_import' => [InquiryCatchUpPlanner::UNSUPPORTED_TECHNICAL_SOURCE],
        'unresolved_manual_decision' => [InquiryCatchUpPlanner::UNRESOLVED_USER_REFERENCE, InquiryCatchUpPlanner::REQUIRES_OWNER_DECISION],
    ];

    /** Причины, по которым запись остаётся без ответственного, а не «ждёт решения вообще». */
    private const ASSIGNEE_REASONS = ['unresolved_assignee', 'invariant_in_progress_without_assignee'];

    /** Категории, которые не требуют ни действия, ни решения. */
    private const SETTLED = ['matched_source', 'unsupported_technical_import'];

    private readonly InquiryBackfillClassifier $classifier;
    private readonly InquiryCatchUpPlanner $planner;

    public function __construct(
        private readonly VerifiedQaConnection $qa,
        private readonly FreezeObserver $observer,
    ) {
        $this->classifier = new InquiryBackfillClassifier($qa);
        $this->planner = new InquiryCatchUpPlanner();
    }

    /** @return array<string, mixed> */
    public function run(FreezeAttestation $attestation, int $chunkSize = InquiryBackfillService::DEFAULT_CHUNK): array
    {
        $snapshot = new InquiryBackfillSnapshot($this->qa);

        $before = $this->observer->observe();
        $snapshotBefore = $snapshot->capture();
        $inquiriesBefore = $this->inquiriesFingerprint();

        $scan = $this->scan($chunkSize);
        $reconcile = (new InquiryReconciler($this->qa))->reconcile($chunkSize);

        $snapshotAfter = $snapshot->capture();
        $inquiriesAfter = $this->inquiriesFingerprint();
        $after = $this->observer->observe();

        $integrity = [
            'source_unchanged' => $snapshotBefore['legacy_fingerprint'] === $snapshotAfter['legacy_fingerprint'],
            'inquiries_unchanged' => $inquiriesBefore === $inquiriesAfter,
            'bookings_unchanged' => $snapshotBefore['bookings'] === $snapshotAfter['bookings'],
            'audit_events_unchanged' => $snapshotBefore['audit_events'] === $snapshotAfter['audit_events'],
            'source_rows' => $snapshotAfter['legacy_rows'],
        ];
        $integrity['stable'] = $integrity['source_unchanged'] && $integrity['inquiries_unchanged']
            && $integrity['bookings_unchanged'] && $integrity['audit_events_unchanged']
            && $snapshotBefore['legacy_rows'] === $snapshotAfter['legacy_rows'];

        $gate = (new InquiryFreezeGate())->evaluate($before, $after, $attestation, $integrity['stable']);

        $accounted = array_sum($scan['report']) === $scan['total'] && $scan['total'] === $snapshotBefore['legacy_rows'];
        $unsettled = array_sum(array_diff_key($scan['report'], array_flip(self::SETTLED)));

        $status = match (true) {
            ! $gate['window_quiescent'] => self::UNSAFE_FREEZE,
            ! $accounted || $unsettled > 0 || ! $reconcile['fully_reconciled'] => self::NOT_CONVERGED,
            default => self::CONVERGED_IN_WINDOW,
        };

        return [
            'mode' => 'convergence_rehearsal',
            'read_only' => true,
            'connection' => ['name' => $this->qa->name, 'driver' => $this->qa->driver],
            'status' => $status,
            'provisional' => $status === self::UNSAFE_FREEZE,
            'production_cutover_authorized' => false,
            'freeze' => $gate,
            'integrity' => $integrity,
            'sources' => [
                'total' => $scan['total'],
                'accounting_complete' => $accounted,
                'report' => $scan['report'],
                'planner_categories' => $scan['categories'],
                'proposed_actions' => $scan['actions'],
                'reasons' => $scan['reasons'],
                'sample_source_ids' => $scan['samples'],
            ],
            'inquiries' => [
                'total' => $reconcile['inquiries_total'],
                'independent' => $reconcile['provenance']['independent'] ?? 0,
                'legitimate_inquiry_side_evolution' => $reconcile['expected_evolution']['draft_edited_evidenced'],
                'draft_audit_evidence' => $reconcile['draft_audit_evidence'],
            ],
            'reconciler' => [
                'verdicts' => $reconcile['verdicts'],
                'structurally_consistent' => $reconcile['structurally_consistent'],
                'fully_reconciled' => $reconcile['fully_reconciled'],
                'incomplete_because' => $reconcile['incomplete_because'],
            ],
            'unresolved_manual_decisions' => ($scan['report']['unresolved_manual_decision'] ?? 0)
                + ($scan['report']['conflicting_current_state'] ?? 0)
                + ($scan['report']['assignee_missing_or_deleted'] ?? 0)
                + ($scan['report']['invalid_client_reference'] ?? 0)
                + ($scan['report']['duplicate_booking_linkage'] ?? 0),
        ];
    }

    /** @return array{total: int, report: array<string, int>, categories: array<string, int>, actions: array<string, int>, reasons: array<string, int>, samples: array<string, list<int>>} */
    private function scan(int $chunkSize): array
    {
        $report = array_fill_keys(array_keys(self::REPORT_CATEGORIES), 0);
        $categories = $actions = $reasons = $samples = [];
        $total = 0;

        IncomingInquiry::on($this->qa->name)
            ->select(InquiryBackfillClassifier::SOURCE_COLUMNS)
            ->chunkById($chunkSize, function (Collection $chunk) use (&$report, &$categories, &$actions, &$reasons, &$samples, &$total): void {
                $statuses = Inquiry::on($this->qa->name)
                    ->whereIn('incoming_inquiry_id', $chunk->pluck('id')->all())
                    ->orderBy('id')
                    ->pluck('status', 'incoming_inquiry_id')
                    ->all();

                foreach ($this->classifier->classify($chunk) as $sourceId => $decision) {
                    $plan = $this->planner->plan($decision, $statuses[$sourceId] ?? null);
                    $key = $this->reportKey($plan);

                    $total++;
                    $report[$key]++;
                    $categories[$plan['category']] = ($categories[$plan['category']] ?? 0) + 1;
                    $actions[$plan['action']] = ($actions[$plan['action']] ?? 0) + 1;

                    foreach ($plan['reasons'] as $reason) {
                        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                    }

                    if (! in_array($key, self::SETTLED, true) && count($samples[$key] ?? []) < self::SAMPLE_LIMIT) {
                        $samples[$key][] = (int) $sourceId;
                    }
                }
            });

        ksort($categories);
        ksort($actions);
        ksort($reasons);
        ksort($samples);

        return compact('total', 'report', 'categories', 'actions', 'reasons', 'samples');
    }

    /** @param array{category: string, action: string, reasons: list<string>} $plan */
    private function reportKey(array $plan): string
    {
        if (in_array($plan['category'], [InquiryCatchUpPlanner::UNRESOLVED_USER_REFERENCE, InquiryCatchUpPlanner::REQUIRES_OWNER_DECISION], true)
            && array_intersect($plan['reasons'], self::ASSIGNEE_REASONS) !== []) {
            return 'assignee_missing_or_deleted';
        }

        foreach (self::REPORT_CATEGORIES as $key => $planned) {
            if (in_array($plan['category'], $planned, true)) {
                return $key;
            }
        }

        // Неизвестная категория планировщика не должна пропасть из счёта.
        return 'unresolved_manual_decision';
    }

    /** Отпечаток таблицы inquiries (хеш порциями; в отчёт идёт только равенство). */
    private function inquiriesFingerprint(): string
    {
        $context = hash_init('sha256');

        DB::connection($this->qa->name)->table('inquiries')->orderBy('id')->chunkById(200, function ($chunk) use ($context): void {
            foreach ($chunk as $row) {
                hash_update($context, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            }
        });

        return hash_final($context);
    }
}
