<?php

namespace App\Services\Inquiries\Backfill;

/**
 * Накопитель счётчиков для dry-run / apply / verify (AOA-03-I2).
 *
 * В отчёт попадают только счётчики, коды причин и числовые id источников (не более
 * SAMPLE_LIMIT на причину): имён, e-mail, телефонов, паспортных данных и payload здесь нет,
 * поэтому память ограничена независимо от объёма таблицы.
 */
final class InquiryBackfillReport
{
    public const SAMPLE_LIMIT = 20;

    public const OUTCOME_CREATED = 'CREATED';
    public const OUTCOME_ALREADY_PRESENT = 'ALREADY_PRESENT';

    /** Коды причин по группам для сводки dry-run. */
    private const BOOKING_REASONS = ['booking_owned_by_other_inquiry', 'duplicate_booking_reference_in_sources'];
    private const UNRESOLVED_REASONS = ['unresolved_assignee', 'unresolved_client', 'client_not_tourist', 'unresolved_actor_reference', 'unresolved_booking'];

    private int $sourceTotal = 0;
    private int $eligible = 0;
    /** @var array<string, int> */
    private array $workflow = [];
    /** @var array<string, int> */
    private array $technical = [];
    /** @var array<string, int> */
    private array $verdicts = [];
    /** @var array<string, int> */
    private array $reasons = [];
    /** @var array<string, list<int>> */
    private array $samples = [];
    /** @var array<string, int> */
    private array $outcomes = [];
    private int $raceResolved = 0;

    public function __construct(private readonly string $mode, private readonly VerifiedQaConnection $qa)
    {
    }

    public function record(InquiryBackfillDecision $decision): void
    {
        $this->sourceTotal++;
        $this->eligible += $decision->eligible ? 1 : 0;

        $workflow = $decision->workflowState !== null && $decision->workflowState !== '' ? $decision->workflowState : 'unknown';
        $this->workflow[$workflow] = ($this->workflow[$workflow] ?? 0) + 1;
        $this->technical[$decision->technicalState] = ($this->technical[$decision->technicalState] ?? 0) + 1;
        $this->verdicts[$decision->verdict] = ($this->verdicts[$decision->verdict] ?? 0) + 1;

        foreach ($decision->reasons as $reason) {
            $this->reasons[$reason] = ($this->reasons[$reason] ?? 0) + 1;
            $this->samples[$reason] ??= [];
            if (count($this->samples[$reason]) < self::SAMPLE_LIMIT) {
                $this->samples[$reason][] = $decision->sourceId;
            }
        }
    }

    /** Итог фактической записи (apply) для одного источника. */
    public function outcome(string $outcome): void
    {
        $this->outcomes[$outcome] = ($this->outcomes[$outcome] ?? 0) + 1;
    }

    public function raceResolved(): void
    {
        $this->raceResolved++;
    }

    public function verdictCount(string $verdict): int
    {
        return $this->verdicts[$verdict] ?? 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        ksort($this->workflow);
        ksort($this->technical);
        ksort($this->verdicts);
        ksort($this->reasons);
        ksort($this->samples);
        ksort($this->outcomes);

        $sum = fn (array $codes): int => array_sum(array_map(fn (string $code) => $this->reasons[$code] ?? 0, $codes));

        $report = [
            'mode' => $this->mode,
            'connection' => ['name' => $this->qa->name, 'driver' => $this->qa->driver],
            'source_total' => $this->sourceTotal,
            'eligible' => $this->eligible,
            'status_distribution' => $this->workflow,
            'technical_state_distribution' => $this->technical,
            'verdicts' => $this->verdicts,
            'summary' => [
                'already_linked' => $this->verdicts[InquiryBackfillDecision::MATCHED] ?? 0,
                'proposed_new_inquiries' => $this->verdicts[InquiryBackfillDecision::NOT_PROCESSED] ?? 0,
                'unsupported_sources' => $this->verdicts[InquiryBackfillDecision::UNSUPPORTED] ?? 0,
                'blocked_sources' => $this->verdicts[InquiryBackfillDecision::BLOCKED] ?? 0,
                'conflicts' => $this->verdicts[InquiryBackfillDecision::CONFLICT] ?? 0,
                'duplicate_source_conflicts' => $this->reasons['duplicate_inquiries_for_source'] ?? 0,
                'booking_linkage_conflicts' => $sum(self::BOOKING_REASONS),
                'unresolved_references' => $sum(self::UNRESOLVED_REASONS),
                'blocked_by_model_invariants' => array_sum(array_map(
                    fn (string $code, int $n) => str_starts_with($code, 'invariant_') || $code === 'model_invariant' ? $n : 0,
                    array_keys($this->reasons),
                    $this->reasons
                )),
            ],
            'reasons' => $this->reasons,
            'sample_source_ids' => $this->samples,
        ];

        if ($this->mode === 'apply') {
            $report['outcomes'] = $this->outcomes;
            $report['race_resolved'] = $this->raceResolved;
        }

        return $report;
    }
}
