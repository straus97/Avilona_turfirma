<?php

namespace App\Console\Commands;

use App\Services\Content\PublicContentImporter;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * NOV-PUBLIC-02: проверка и импорт публичного контента из локального файла.
 *
 *   php artisan content:import-public path/to/content.json            (только проверка)
 *   php artisan content:import-public path/to/content.json --commit   (запись в пустые таблицы)
 *
 * Подключения к исходным базам нет: вход — только файл формата
 * avilona-public-content/1. Содержимое записей в вывод не попадает — только
 * счётчики и сообщения об ошибках с координатами записи.
 */
class ImportPublicContentCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'content:import-public
        {file : JSON-файл формата avilona-public-content/1}
        {--commit : Записать данные (без флага выполняется только проверка)}
        {--force : Не спрашивать подтверждение в production}';

    protected $description = 'Validate (default) or import public website content from a JSON exchange file into EMPTY content tables. No users, bookings, inquiries or reviews.';

    public function handle(PublicContentImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file) || filesize($file) > 32 * 1024 * 1024) {
            $this->error('Файл не найден, недоступен или больше 32 МБ.');

            return self::FAILURE;
        }

        $result = $importer->validate((string) file_get_contents($file));

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($result['errors'] as $error) {
            $this->error($error);
        }
        foreach ($result['counts'] as $table => $count) {
            $this->line(sprintf('%-20s %d', $table, $count));
        }

        if (! $result['ok']) {
            $this->error('Проверка не пройдена. Ничего не записано.');

            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $this->info('Проверка пройдена. Режим dry-run: ничего не записано (добавьте --commit для записи).');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $inserted = $importer->commit();
        } catch (\Throwable $e) {
            $this->error('Импорт отменён, транзакция откатена: ' . get_class($e) . ' — ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($inserted as $table => $count) {
            $this->info("{$table}: вставлено {$count}");
        }

        return self::SUCCESS;
    }
}
