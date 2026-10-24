<?php

namespace App\Console\Commands;

use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryBackfillVerifier;
use App\Services\Inquiries\Backfill\InquiryReconciler;
use App\Services\Inquiries\Backfill\UnsafeBackfillConnection;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-03-I2: репетиция переноса IncomingInquiry → Inquiry на ОДНОРАЗОВОЙ QA-базе.
 *
 * По умолчанию — dry-run (ничего не пишется). Запись только с --apply и
 * --confirm-database=<имя базы>. Подключение по умолчанию (оно может быть каноническим)
 * не используется никогда: нужен явный --connection, который проходит положительную проверку
 * VerifiedQaConnection ДО первого запроса к базе. Команда не предназначена для боевых данных.
 */
class BackfillInquiriesCommand extends Command
{
    protected $signature = 'inquiries:backfill
        {--connection= : Явное имя одноразового QA-подключения (обязательно)}
        {--apply : Записать недостающие Inquiry (без флага — только dry-run)}
        {--confirm-database= : Имя QA-базы; обязательно вместе с --apply}
        {--verify : Только сверить IncomingInquiry и Inquiry (без записи)}
        {--reconcile : AOA-03-I3: переходная сверка пар IncomingInquiry ↔ Inquiry (только чтение)}
        {--chunk=200 : Размер порции (1-1000)}
        {--json : Машиночитаемый отчёт}';

    protected $description = 'AOA-03-I2: dry-run / apply / verify переноса обращений на одноразовой QA-базе (не для боевых данных)';

    public function handle(): int
    {
        try {
            $qa = VerifiedQaConnection::verify($this->option('connection'));
        } catch (UnsafeBackfillConnection $e) {
            $this->error('REFUSED: ' . $e->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $verify = (bool) $this->option('verify');
        $reconcile = (bool) $this->option('reconcile');

        if ($apply + $verify + $reconcile > 1) {
            $this->error('Use only one of --apply, --verify or --reconcile.');

            return self::FAILURE;
        }

        if ($apply && (string) $this->option('confirm-database') !== $qa->database) {
            $this->error('REFUSED: --apply requires --confirm-database equal to the QA database name.');

            return self::FAILURE;
        }

        $chunk = (int) $this->option('chunk');
        if ($chunk < 1 || $chunk > 1000) {
            $this->error('The chunk size must be between 1 and 1000.');

            return self::FAILURE;
        }

        if (! Schema::connection($qa->name)->hasTable('inquiries') || ! Schema::connection($qa->name)->hasTable('incoming_inquiries')) {
            $this->error('The QA database lacks the inquiries / incoming_inquiries tables (run migrations on the QA database first).');

            return self::FAILURE;
        }

        if ($reconcile) {
            $report = (new InquiryReconciler($qa))->reconcile($chunk);
            $exit = $report['fully_reconciled'] ? self::SUCCESS : self::FAILURE;
        } elseif ($verify) {
            $report = (new InquiryBackfillVerifier($qa))->verify($chunk);
            $exit = $report['fully_verified'] ? self::SUCCESS : self::FAILURE;
        } elseif ($apply) {
            $report = (new InquiryBackfillService($qa))->apply($chunk);
            $exit = $report['integrity']['ok'] ? self::SUCCESS : self::FAILURE;
        } else {
            $report = (new InquiryBackfillService($qa))->dryRun($chunk);
            $exit = self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->renderText($report);
        }

        return $exit;
    }

    /** @param array<string, mixed> $report */
    private function renderText(array $report): void
    {
        $this->line(strtoupper(str_replace('_', ' ', (string) $report['mode'])) . ($report['mode'] === 'dry_run' ? ' — NO WRITES' : ''));
        $this->line('source records: ' . $report['source_total'] . ', eligible: ' . $report['eligible']);

        foreach (['status_distribution', 'technical_state_distribution', 'verdicts', 'summary', 'reasons', 'outcomes', 'integrity', 'global', 'provenance', 'direction_of_difference', 'expected_evolution', 'incomplete_because'] as $section) {
            if (! isset($report[$section])) {
                continue;
            }
            $this->line($section . ':');
            foreach ($report[$section] as $key => $value) {
                $this->line('  ' . $key . ': ' . (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value));
            }
        }

        if (isset($report['unprocessed_sources'])) {
            $this->line('unprocessed sources: ' . $report['unprocessed_sources']['total']);
        }

        foreach (['inquiries_created', 'writes_performed', 'race_resolved', 'fully_verified', 'structurally_consistent', 'fully_reconciled'] as $key) {
            if (isset($report[$key])) {
                $this->line($key . ': ' . (is_bool($report[$key]) ? ($report[$key] ? 'yes' : 'no') : (string) $report[$key]));
            }
        }
    }
}
