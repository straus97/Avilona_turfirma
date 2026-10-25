<?php

namespace App\Console\Commands;

use App\Services\Inquiries\Backfill\UnsafeBackfillConnection;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\Convergence\DatabaseFreezeObserver;
use App\Services\Inquiries\Convergence\FreezeAttestation;
use App\Services\Inquiries\Convergence\InquiryConvergenceRehearsal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * AOA-03-I5: ТОЛЬКО ЧТЕНИЕ. Полное сравнение источников и Inquiry внутри наблюдаемого окна на
 * ОДНОРАЗОВОЙ QA-базе. Команда не имеет режима записи вообще, не использует подключение по
 * умолчанию и отказывается от любого подключения, не прошедшего VerifiedQaConnection, ДО первого
 * запроса. Код возврата 0 — только при статусе CONVERGED_IN_WINDOW.
 */
class InquiryConvergenceRehearsalCommand extends Command
{
    protected $signature = 'inquiries:convergence-rehearsal
        {--connection= : Явное имя одноразового QA-подключения (обязательно)}
        {--attest= : Заявления оператора через запятую: ingress_stopped,queue_workers_stopped,scheduler_paused,no_manual_sql_or_maintenance_commands}
        {--chunk=200 : Размер порции (1-1000)}
        {--json : Машиночитаемый отчёт}';

    protected $description = 'AOA-03-I5: read-only repetition of the full IncomingInquiry/Inquiry comparison inside an observed window (disposable QA database only)';

    public function handle(): int
    {
        try {
            $qa = VerifiedQaConnection::verify($this->option('connection'));
        } catch (UnsafeBackfillConnection $e) {
            $this->error('REFUSED: ' . $e->getMessage());

            return self::FAILURE;
        }

        $chunk = (int) $this->option('chunk');
        if ($chunk < 1 || $chunk > 1000) {
            $this->error('The chunk size must be between 1 and 1000.');

            return self::FAILURE;
        }

        try {
            $declared = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('attest')))));
            $attestation = new FreezeAttestation($declared);
        } catch (InvalidArgumentException $e) {
            $this->error('REFUSED: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! Schema::connection($qa->name)->hasTable('inquiries') || ! Schema::connection($qa->name)->hasTable('incoming_inquiries')) {
            $this->error('The QA database lacks the inquiries / incoming_inquiries tables (run migrations on the QA database first).');

            return self::FAILURE;
        }

        $report = (new InquiryConvergenceRehearsal($qa, new DatabaseFreezeObserver($qa)))->run($attestation, $chunk);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->renderText($report);
        }

        return $report['status'] === InquiryConvergenceRehearsal::CONVERGED_IN_WINDOW ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $report */
    private function renderText(array $report): void
    {
        $this->line('CONVERGENCE REHEARSAL — READ ONLY — ' . $report['status'] . ($report['provisional'] ? ' (provisional counts)' : ''));
        $this->line('production cutover authorized: no');
        $this->line('window quiescent: ' . ($report['freeze']['window_quiescent'] ? 'yes' : 'no'));

        foreach (['blockers', 'unverifiable', 'attestation_missing'] as $key) {
            $this->line('  ' . $key . ': ' . ($report['freeze'][$key] === [] ? '-' : implode(', ', $report['freeze'][$key])));
        }

        $this->line('sources: ' . $report['sources']['total'] . ' (accounting complete: ' . ($report['sources']['accounting_complete'] ? 'yes' : 'no') . ')');
        foreach ($report['sources']['report'] as $key => $count) {
            $this->line('  ' . $key . ': ' . $count);
        }

        $this->line('legitimate inquiry-side evolution: ' . $report['inquiries']['legitimate_inquiry_side_evolution']);
        $this->line('independent inquiries: ' . $report['inquiries']['independent']);
        $this->line('fully reconciled: ' . ($report['reconciler']['fully_reconciled'] ? 'yes' : 'no'));
    }
}
