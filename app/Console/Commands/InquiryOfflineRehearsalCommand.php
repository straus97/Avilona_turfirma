<?php

namespace App\Console\Commands;

use App\Services\Inquiries\Backfill\UnsafeBackfillConnection;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\Convergence\FreezeAttestation;
use App\Services\Inquiries\Convergence\OfflineConvergenceRehearsal;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * AOA-03-I6: репетиция начальной офлайн-сходимости на ЗАПЕЧАТАННОМ СИНТЕТИЧЕСКОМ снимке одноразовой
 * QA-базы. Три режима:
 *   --seal                       запечатать снимок: предпроверки + манифест (JSON в stdout), записи нет;
 *   --manifest=<файл>            сравнить со снимком и проверить сходимость без записи (dry-run);
 *   --manifest=<файл> --apply --confirm-database=<имя>   то же + существующий перенос I2.
 *
 * Подключение по умолчанию не используется никогда; подключение проходит VerifiedQaConnection ДО первого
 * запроса, затем сервер сам подтверждает свою идентичность. Команда не имеет режима для боевых данных:
 * адрес вне зарезервированных доменов отклоняется. Код возврата 0 — только CONVERGED_OFFLINE
 * (для --seal — только если снимок запечатан); это не разрешение на cutover.
 */
class InquiryOfflineRehearsalCommand extends Command
{
    protected $signature = 'inquiries:offline-rehearsal
        {--connection= : Явное имя одноразового QA-подключения (обязательно)}
        {--seal : Запечатать снимок и вывести манифест (без записи)}
        {--manifest= : Путь к JSON-манифесту, выданному --seal}
        {--apply : После предпроверок выполнить существующий перенос I2 (без флага записи нет)}
        {--confirm-database= : Имя QA-базы; обязательно вместе с --apply}
        {--attest= : Заявления оператора через запятую (см. inquiries:convergence-rehearsal)}
        {--chunk=200 : Размер порции (1-1000)}
        {--json : Машиночитаемый отчёт}';

    protected $description = 'AOA-03-I6: sealed synthetic offline-snapshot convergence rehearsal (disposable QA database only; never real data)';

    public function handle(): int
    {
        try {
            $qa = VerifiedQaConnection::verify($this->option('connection'));
        } catch (UnsafeBackfillConnection $e) {
            $this->error('REFUSED: ' . $e->getMessage());

            return self::FAILURE;
        }

        $seal = (bool) $this->option('seal');
        $apply = (bool) $this->option('apply');
        $manifestPath = $this->option('manifest');

        if ($seal && ($apply || $manifestPath !== null)) {
            $this->error('--seal cannot be combined with --apply or --manifest.');

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

        try {
            $declared = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('attest')))));
            $attestation = new FreezeAttestation($declared);
        } catch (InvalidArgumentException $e) {
            $this->error('REFUSED: ' . $e->getMessage());

            return self::FAILURE;
        }

        $manifest = null;
        if (! $seal && $manifestPath !== null) {
            $manifest = $this->readManifest((string) $manifestPath);
            if ($manifest === null) {
                $this->error('REFUSED: the manifest is missing, unreadable or not a JSON object.');

                return self::FAILURE;
            }
        }

        $rehearsal = new OfflineConvergenceRehearsal($qa);

        if ($seal) {
            $report = $rehearsal->seal($attestation);
            $exit = $report['sealed'] ? self::SUCCESS : self::FAILURE;
        } else {
            $report = $rehearsal->run($manifest, $attestation, $apply, $chunk);
            $exit = $report['status'] === OfflineConvergenceRehearsal::CONVERGED_OFFLINE ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('json') || $seal) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->renderText($report);
        }

        return $exit;
    }

    /** @return array<string, mixed>|null */
    private function readManifest(string $path): ?array
    {
        if ($path === '' || ! is_file($path) || filesize($path) > 1_000_000) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        // Допустим как «голый» манифест, так и целый вывод --seal.
        if (is_array($decoded) && isset($decoded['manifest']) && is_array($decoded['manifest'])) {
            $decoded = $decoded['manifest'];
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $report */
    private function renderText(array $report): void
    {
        $this->line('OFFLINE CONVERGENCE REHEARSAL — ' . $report['status'] . ($report['apply_attempted'] ? ' (apply)' : ' (no writes)'));
        $this->line('production cutover authorized: no; B1 OPEN; B2 OPEN; capture completeness NOT established');

        if ($report['refusals'] !== []) {
            $this->line('refusals: ' . implode(', ', $report['refusals']));
        }

        foreach ($report['accounting']['requested_categories'] ?? [] as $key => $count) {
            $this->line('  ' . $key . ': ' . $count);
        }

        $this->line('decisions required: ' . ($report['accounting']['decisions_required'] ?? '-'));
    }
}
