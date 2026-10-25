<?php

namespace App\Services\Inquiries\Convergence;

/**
 * Оценка «окна без записи» вокруг полного сравнения (AOA-03-I5). Чистая функция двух замеров,
 * заявлений оператора и признака «отпечаток источника не изменился»: ни базы, ни записи.
 *
 * Это НЕ барьер записи и не готовность к заморозке в бою. Результат `window_quiescent = true`
 * означает ровно одно: за наблюдаемое окно нигде не нашлось признаков записи, а всё, что нельзя
 * проверить из базы, оператор письменно заявил. Любая непроверяемая часть замера — отказ
 * (fail closed), а не «наверное, чисто». Ни при каких входах разрешения на cutover не выдаётся:
 * `production_cutover_authorized` всегда false, а список причин постоянен.
 */
final class InquiryFreezeGate
{
    /** Постоянные причины, по которым боевой cutover не разрешается ничем из этого класса. */
    public const CUTOVER_BLOCKERS = [
        'lossless_intake_unproven' => 'No verified no-loss intake contract: a webhook rejected during maintenance/ingress stop is persisted nowhere, and provider retry of such a response is not proven.',
        'no_write_barrier_in_application' => 'The application has no verified write barrier; in-flight transactions, queue workers and sessions are observed, not blocked.',
        'catch_up_apply_not_implemented' => 'Only a read-only comparison exists; there is no reviewed apply/mirror step for post-backfill differences.',
        'source_of_truth_switch_not_implemented' => 'E5 stays authoritative; no runtime switch to Inquiry exists.',
        'no_owner_authorization_for_real_data' => 'The owner has not authorized any real-data backup, copy, rehearsal or backfill.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function evaluate(FreezeObservation $before, FreezeObservation $after, FreezeAttestation $attestation, bool $sourceUnchanged): array
    {
        $blockers = [];
        $unverifiable = [];

        foreach ([
            'other_sessions' => 'sessions_not_observable',
            'other_open_transactions' => 'transactions_not_observable',
            'import_jobs_queued' => 'queue_not_observable',
        ] as $field => $reason) {
            if ($before->{$this->property($field)} === null || $after->{$this->property($field)} === null) {
                $unverifiable[] = $reason;
            }
        }

        if ($before->sourceWriteCounters === null || $after->sourceWriteCounters === null) {
            $unverifiable[] = 'write_counters_not_observable';
        }

        foreach ([$before, $after] as $observation) {
            if (($observation->otherSessions ?? 0) > 0) {
                $blockers[] = 'other_sessions_present';
            }
            if (($observation->otherOpenTransactions ?? 0) > 0) {
                $blockers[] = 'open_transaction_present';
            }
            if (($observation->importJobsQueued ?? 0) > 0 || ($observation->importJobsReserved ?? 0) > 0) {
                $blockers[] = 'import_jobs_queued_or_reserved';
            }
            if ($observation->importsInFlight > 0) {
                $blockers[] = 'import_in_flight';
            }
        }

        if ($before->sourceWriteCounters !== null && $after->sourceWriteCounters !== null
            && $before->sourceWriteCounters !== $after->sourceWriteCounters) {
            $blockers[] = 'source_write_observed_in_window';
        }

        if (! $sourceUnchanged) {
            $blockers[] = 'source_changed_during_scan';
        }

        $missing = $attestation->missing();
        if ($missing !== []) {
            $blockers[] = 'attestation_incomplete';
        }

        $blockers = array_values(array_unique($blockers));
        $unverifiable = array_values(array_unique($unverifiable));

        return [
            'window_quiescent' => $blockers === [] && $unverifiable === [],
            'blockers' => $blockers,
            'unverifiable' => $unverifiable,
            'attested' => $attestation->declared(),
            'attestation_missing' => $missing,
            'attestation_note' => 'Attested items are operator statements, not machine-verified.',
            'observations' => ['before' => $before->toArray(), 'after' => $after->toArray()],
            'production_cutover_authorized' => false,
            'cutover_blockers' => self::CUTOVER_BLOCKERS,
        ];
    }

    private function property(string $field): string
    {
        return lcfirst(str_replace('_', '', ucwords($field, '_')));
    }
}
