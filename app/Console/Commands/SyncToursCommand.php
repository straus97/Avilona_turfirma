<?php

namespace App\Console\Commands;

use App\Models\TourOperator;
use App\Services\TourOperators\CoralTravelService;
use Illuminate\Console\Command;

class SyncToursCommand extends Command
{
    protected $signature = 'tours:sync {--operator= : Sync specific operator} {--filters= : JSON filters}';
    protected $description = 'Sync tours from tour operators';

    public function handle()
    {
        $operatorName = $this->option('operator');
        $filtersJson = $this->option('filters');
        
        $filters = $filtersJson ? json_decode($filtersJson, true) : [];

        if ($operatorName) {
            $this->syncOperator($operatorName, $filters);
        } else {
            $this->syncAllOperators($filters);
        }

        return 0;
    }

    protected function syncOperator(string $operatorName, array $filters = [])
    {
        $operator = TourOperator::where('name', $operatorName)->first();
        
        if (!$operator) {
            $this->error("Operator '{$operatorName}' not found");
            return;
        }

        if (!$operator->canSync()) {
            $this->warn("Operator '{$operatorName}' is not ready for sync");
            return;
        }

        $this->info("Syncing tours from {$operatorName}...");

        $service = $this->getServiceForOperator($operator);
        if (!$service) {
            $this->error("No service available for operator '{$operatorName}'");
            return;
        }

        $success = $service->syncTours($filters);
        
        if ($success) {
            $this->info("✅ Successfully synced tours from {$operatorName}");
        } else {
            $this->error("❌ Failed to sync tours from {$operatorName}");
        }
    }

    protected function syncAllOperators(array $filters = [])
    {
        $operators = TourOperator::active()
            ->autoSync()
            ->needsSync()
            ->get();

        if ($operators->isEmpty()) {
            $this->info("No operators need syncing");
            return;
        }

        $this->info("Found {$operators->count()} operators to sync");

        foreach ($operators as $operator) {
            $this->syncOperator($operator->name, $filters);
        }
    }

    protected function getServiceForOperator(TourOperator $operator)
    {
        switch ($operator->name) {
            case 'Coral Travel':
                return new CoralTravelService($operator);
            // Добавить другие туроператоры по мере необходимости
            default:
                return null;
        }
    }
}
