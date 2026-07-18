<?php

namespace App\Console\Commands;

use App\Services\News\RssNewsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncNewsRssCommand extends Command
{
    protected $signature = 'news:sync-rss';

    protected $description = 'Synchronize the public news table from the external RSS feed';

    public function handle(RssNewsSyncService $service): int
    {
        try {
            $result = $service->sync();
        } catch (\Throwable $e) {
            // Единственный владелец логирования: сервис бросает исключение, команда логирует один раз.
            // В консоль — только фиксированное санитизированное сообщение (без деталей исключения).
            Log::error('RSS news sync failed', [
                'exception' => $e,
            ]);
            $this->error('RSS news sync failed. See application log for details.');

            return Command::FAILURE;
        }

        $skippedCount = count($result['skipped']);
        $this->info("Synced {$result['synced']} news items, skipped {$skippedCount}.");

        return Command::SUCCESS;
    }
}
