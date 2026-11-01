<?php

namespace App\Console\Commands;

use App\Services\Inquiries\Backfill\UnsafeBackfillConnection;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Seo\LegacyUrlClassifier;
use App\Support\Seo\RobotsRules;
use Illuminate\Console\Command;

/**
 * NOV-PUBLIC-03A: разбор выгрузок владельца из Яндекс Вебмастера / Google Search
 * Console относительно текущих маршрутов. Только чтение.
 *
 *   php artisan seo:audit-urls path/to/export.csv
 *   php artisan seo:audit-urls path/to/export.csv --check-db --connection=aoa_qa   (только одноразовая QA-база)
 *
 * Формат входного CSV (UTF-8, запятая или «;»): первая строка — заголовок.
 *   url      — обязательный: полный URL или путь (/countries/egipet?page=2)
 *   source   — необязательный: indexed | not_indexed_404 | crawled_not_indexed | sitemap | other
 *   note     — необязательный: свободный текст владельца (в отчёт копируется как данные)
 * Других колонок команда не ждёт и не использует; метрик не придумывает.
 *
 * Сеть, аккаунты поисковиков и обход сайта не используются: вход — только файл.
 * Без --check-db база не открывается вообще. С --check-db обязателен --connection=<имя>, и
 * подключение проходит VerifiedQaConnection (AOA-03-I2) ДО первого запроса: только
 * qa_disposable, loopback, не порты 3306/3308, не каноническая база, не подключение по
 * умолчанию. Иначе — отказ. Подключение по умолчанию (.env) не используется никогда;
 * общего обхода проверки нет. Аудит staging-копии — отдельная операция по решению владельца.
 * Никаких redirect-таблиц команда не создаёт: класс в отчёте — основание для
 * разговора с владельцем, а не готовое сопоставление.
 */
class AuditLegacyUrlsCommand extends Command
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_ROWS = 50000;
    private const SOURCES = ['indexed', 'not_indexed_404', 'crawled_not_indexed', 'sitemap', 'other', ''];

    protected $signature = 'seo:audit-urls
        {file : CSV с колонкой url (опционально source, note)}
        {--output= : Записать построчный отчёт в CSV (существующий файл не перезаписывается)}
        {--check-db : Проверить наличие slug в ОДНОРАЗОВОЙ QA-базе (только exists(); нужен --connection)}
        {--connection= : Имя проверенного одноразового QA-подключения для --check-db}';

    protected $description = 'Read-only audit of exported legacy URLs against current public routes. No network, no search-engine accounts, no redirect table is generated.';

    public function handle(LegacyUrlClassifier $classifier): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file) || filesize($file) > self::MAX_BYTES) {
            $this->error('Файл не найден, недоступен или больше 5 МБ.');

            return self::FAILURE;
        }

        $output = $this->option('output');
        if (is_string($output) && $output !== '' && file_exists($output)) {
            $this->error('Файл отчёта уже существует; команда его не перезаписывает.');

            return self::FAILURE;
        }

        $rows = $this->readRows($file);
        if ($rows === null) {
            $this->error('Не найдена колонка url в первой строке или в файле больше ' . self::MAX_ROWS . ' строк.');

            return self::FAILURE;
        }

        $legacy = $this->legacySitemapSet();
        $robots = RobotsRules::fromFile(base_path('robots.txt'));
        $qaConnection = null;

        if ($this->option('check-db')) {
            try {
                $qaConnection = VerifiedQaConnection::verify($this->option('connection'));
                $this->assertServerIdentity($qaConnection);
            } catch (UnsafeBackfillConnection $e) {
                $this->error('REFUSED: ' . $e->getMessage());

                return self::FAILURE;
            }

            $this->warn('--check-db: только exists() по slug; QA-подключение «' . $qaConnection->name . '» проверено.');
        } elseif ($this->option('connection') !== null) {
            $this->error('--connection имеет смысл только вместе с --check-db.');

            return self::FAILURE;
        }

        $report = [];
        $totals = [];
        foreach ($rows as $row) {
            $c = $classifier->classify($row['url'], $qaConnection?->name);
            $inLegacy = $c['path'] !== '' && isset($legacy[$c['path'] . ($c['query'] !== '' ? '?' . $c['query'] : '')]);
            $blocked = $c['path'] !== '' && ! $robots->allows($c['path'] . ($c['query'] !== '' ? '?' . $c['query'] : ''));
            $target = LegacyUrlClassifier::firstPageTarget($c);

            $totals[$c['class']] = ($totals[$c['class']] ?? 0) + 1;
            $report[] = [
                $row['url'], $row['source'], $c['class'], $c['action'], $c['route'],
                $target ?? '', $inLegacy ? 'yes' : 'no', $blocked ? 'yes' : 'no', $row['note'],
            ];
        }

        ksort($totals);
        $this->table(['class', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($totals), $totals));
        $this->line('Строк: ' . count($report));

        if (is_string($output) && $output !== '') {
            $this->writeReport($output, $report);
            $this->info('Отчёт записан: ' . $output);
        }

        return self::SUCCESS;
    }

    /**
     * Для MySQL: сервер сам называет базу и порт; расхождение с проверенной конфигурацией
     * (например, проброшенный порт на другую базу) — отказ. SQLite проверен путём в конфигурации.
     */
    private function assertServerIdentity(VerifiedQaConnection $qa): void
    {
        if ($qa->driver === 'sqlite') {
            return;
        }

        try {
            $row = \Illuminate\Support\Facades\DB::connection($qa->name)->selectOne('select database() as db, @@port as port');
        } catch (\Throwable) {
            throw new UnsafeBackfillConnection('The QA server identity could not be verified.');
        }

        $expectedPort = (int) config('database.connections.' . $qa->name . '.port');

        if ($row === null || strcasecmp((string) $row->db, $qa->database) !== 0 || (int) $row->port !== $expectedPort) {
            throw new UnsafeBackfillConnection('The server identity does not match the verified QA configuration.');
        }
    }

    /** @return array<int, array{url: string, source: string, note: string}>|null */
    private function readRows(string $file): ?array
    {
        $handle = fopen($file, 'rb');
        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! is_array($header)) {
            fclose($handle);

            return null;
        }

        $header = array_map(fn ($h) => strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF"))), $header);
        $urlIndex = array_search('url', $header, true);
        if ($urlIndex === false) {
            fclose($handle);

            return null;
        }
        $sourceIndex = array_search('source', $header, true);
        $noteIndex = array_search('note', $header, true);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($data === [null] || ! isset($data[$urlIndex]) || trim($data[$urlIndex]) === '') {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return null;
            }

            $source = $sourceIndex !== false ? strtolower(trim((string) ($data[$sourceIndex] ?? ''))) : '';

            $rows[] = [
                'url' => trim($data[$urlIndex]),
                'source' => in_array($source, self::SOURCES, true) ? $source : 'other',
                'note' => $noteIndex !== false ? (string) ($data[$noteIndex] ?? '') : '',
            ];
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<string, true> Пути (с query) из sitemap старого сайта, сохранённого в репозитории. */
    private function legacySitemapSet(): array
    {
        $path = resource_path('seo/legacy-sitemap-paths.txt');
        $lines = is_file($path) ? preg_split('/\R/', trim((string) file_get_contents($path))) : [];

        return array_fill_keys(array_filter($lines), true);
    }

    /** @param array<int, array<int, string>> $report */
    private function writeReport(string $path, array $report): void
    {
        $handle = fopen($path, 'xb');
        fputcsv($handle, ['url', 'source', 'class', 'action', 'route', 'redirect_target', 'in_legacy_sitemap', 'blocked_by_robots', 'note'], ',', '"', '');

        foreach ($report as $row) {
            fputcsv($handle, array_map([$this, 'neutralizeFormula'], $row), ',', '"', '');
        }

        fclose($handle);
    }

    /** Ячейка, начинающаяся с = + - @, в таблице превратилась бы в формулу: защищаем префиксом. */
    private function neutralizeFormula(string $cell): string
    {
        return $cell !== '' && strpbrk($cell[0], "=+-@\t\r") !== false ? "'" . $cell : $cell;
    }
}
