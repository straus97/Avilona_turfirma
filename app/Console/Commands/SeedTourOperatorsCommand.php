<?php

namespace App\Console\Commands;

use App\Models\TourOperator;
use Illuminate\Console\Command;

class SeedTourOperatorsCommand extends Command
{
    protected $signature = 'operators:seed';
    protected $description = 'Seed tour operators data';

    public function handle()
    {
        $operators = [
            [
                'name' => 'Coral Travel',
                'api_endpoint' => 'https://api.coral.ru',
                'api_key' => 'coral_api_key_here',
                'api_secret' => 'coral_api_secret_here',
                'api_config' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'rate_limit' => 100, // запросов в минуту
                ],
                'sync_interval' => 60, // каждые 60 минут
            ],
            [
                'name' => 'Pegas',
                'api_endpoint' => 'https://api.pegas.ru',
                'api_key' => 'pegas_api_key_here',
                'api_secret' => 'pegas_api_secret_here',
                'api_config' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'rate_limit' => 80,
                ],
                'sync_interval' => 90, // каждые 90 минут
            ],
            [
                'name' => 'Tez Tour',
                'api_endpoint' => 'https://api.teztour.ru',
                'api_key' => 'teztour_api_key_here',
                'api_secret' => 'teztour_api_secret_here',
                'api_config' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'rate_limit' => 120,
                ],
                'sync_interval' => 45, // каждые 45 минут
            ],
            [
                'name' => 'Anex Tour',
                'api_endpoint' => 'https://api.anex.ru',
                'api_key' => 'anex_api_key_here',
                'api_secret' => 'anex_api_secret_here',
                'api_config' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'rate_limit' => 90,
                ],
                'sync_interval' => 75, // каждые 75 минут
            ],
            [
                'name' => 'TUI',
                'api_endpoint' => 'https://api.tui.ru',
                'api_key' => 'tui_api_key_here',
                'api_secret' => 'tui_api_secret_here',
                'api_config' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'rate_limit' => 110,
                ],
                'sync_interval' => 60, // каждые 60 минут
            ],
        ];

        foreach ($operators as $operatorData) {
            TourOperator::updateOrCreate(
                ['name' => $operatorData['name']],
                $operatorData
            );
            
            $this->info("✅ Created/updated operator: {$operatorData['name']}");
        }

        $this->info("🎉 Successfully seeded " . count($operators) . " tour operators");
        return 0;
    }
}
