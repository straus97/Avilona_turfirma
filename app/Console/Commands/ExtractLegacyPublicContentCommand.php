<?php

namespace App\Console\Commands;

use App\Services\Content\Legacy\LegacyPublicContentPipeline;
use App\Services\Content\Legacy\LegacySqlDumpException;
use App\Services\Content\Legacy\LegacySqlDumpParser;
use Illuminate\Console\Command;

/**
 * NOV-PUBLIC-04A: офлайн-подготовка публичного контента из ИСТОРИЧЕСКОЙ SQL-выгрузки.
 *
 *   php artisan content:extract-legacy-dump "C:\...\dump.sql" --out="C:\...\out" [--images="C:\...\img.zip"]
 *
 * Выгрузка читается как текст и никуда не выполняется: подключения к базе данных
 * нет (ни основного, ни одноразового). Результат — файл avilona-public-content/1 для
 * `content:import-public` (dry-run) и приватные отчёты. Выгрузка, архив и результат
 * должны лежать ВНЕ репозитория; команда отказывается писать в рабочую копию Git.
 * В консоль выводятся только счётчики и коды: тексты записей и данные приватных
 * таблиц не печатаются никогда.
 */
class ExtractLegacyPublicContentCommand extends Command
{
    protected $signature = 'content:extract-legacy-dump
        {dump : Исторический .sql (phpMyAdmin/mysqldump); выполняется НЕ будет}
        {--out= : Новый или пустой каталог для результата (вне репозитория)}
        {--images= : Архив изображений .zip (инспектируется без распаковки)}
        {--acknowledge-unknown= : Через запятую: неизвестные таблицы, которые владелец явно исключает из обработки}';

    protected $description = 'Offline: turn a legacy SQL dump (parsed as data, never executed) into an avilona-public-content/1 file plus private review reports. No database connection.';

    public function handle(): int
    {
        $dump = (string) $this->argument('dump');
        $out = (string) $this->option('out');
        $images = $this->option('images');
        $images = is_string($images) && $images !== '' ? $images : null;

        if ($out === '') {
            $this->error('Укажите --out=<каталог вне репозитория>.');

            return self::FAILURE;
        }
        if (! is_file($dump) || ! is_readable($dump) || filesize($dump) > LegacySqlDumpParser::MAX_BYTES) {
            $this->error('Выгрузка не найдена, недоступна или больше ' . (LegacySqlDumpParser::MAX_BYTES >> 20) . ' МБ.');

            return self::FAILURE;
        }
        if ($images !== null && (! is_file($images) || ! is_readable($images))) {
            $this->error('Архив изображений не найден или недоступен.');

            return self::FAILURE;
        }

        try {
            foreach (array_filter([$dump, $images, $out]) as $path) {
                LegacyPublicContentPipeline::assertOutsideRepository($path);
            }
        } catch (\InvalidArgumentException) {
            $this->error('REFUSED: выгрузка, архив и каталог результата должны находиться вне репозитория.');

            return self::FAILURE;
        }

        if (file_exists($out) && (! is_dir($out) || count(array_diff(scandir($out) ?: [], ['.', '..'])) > 0)) {
            $this->error('REFUSED: каталог результата уже существует и не пуст; существующие файлы не перезаписываются.');

            return self::FAILURE;
        }

        $acknowledged = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('acknowledge-unknown')))));
        $pipeline = LegacyPublicContentPipeline::make();

        try {
            $result = $pipeline->run($dump, $images, $acknowledged, base_path('resources/seo/legacy-sitemap-paths.txt'));
        } catch (LegacySqlDumpException $e) {
            $this->error('REFUSED: выгрузка отвергнута — ' . $e->getMessage());

            return self::FAILURE;
        } catch (\RuntimeException $e) {
            $this->error('REFUSED: ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($pipeline->summaryLines($result['report']) as $line) {
            $this->line($line);
        }

        $written = $pipeline->write($result, $out);
        $this->line('written: ' . implode(', ', $written));

        if ($result['report']['status'] === 'BLOCKED_UNKNOWN_TABLES') {
            $this->error('BLOCKED: в выгрузке есть неизвестные таблицы (' . implode(', ', $result['report']['unacknowledged_unknown_tables'])
                . '). Публичный файл не создан. Если таблица заведомо не публичная, назовите её в --acknowledge-unknown.');

            return self::FAILURE;
        }

        $this->info('Готово. Это офлайн-подготовка: ничего не импортировано и не развёрнуто. Проверьте report.md и сделайте dry-run: content:import-public public-content.json');

        return self::SUCCESS;
    }
}
