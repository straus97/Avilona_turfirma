<?php

namespace App\Console\Commands;

use App\Models\Best_offer;
use App\Models\News;
use App\Models\Partner;
use App\Models\Reviews;
use App\Models\Countries_image;
use App\Models\Employee;
use App\Models\Award;
use App\Models\Tour;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Console\Command;

class CheckDatabaseData extends Command
{
    protected $signature = 'db:check {--table= : Check specific table}';
    protected $description = 'Проверка данных в базе данных';

    public function handle()
    {
        $table = $this->option('table');
        
        if ($table) {
            $this->checkTable($table);
        } else {
            $this->checkAllTables();
        }
        
        return 0;
    }

    private function checkAllTables()
    {
        $this->info('📊 Проверка данных в базе данных...');
        $this->newLine();
        
        $tables = [
            'best_offers' => Best_offer::class,
            'reviews' => Reviews::class,
            'news' => News::class,
            'partners' => Partner::class,
            'countries' => Countries_image::class,
            'employees' => Employee::class,
            'awards' => Award::class,
            'tours' => Tour::class,
            'bookings' => Booking::class,
            'users' => User::class,
        ];
        
        $total = 0;
        foreach ($tables as $name => $model) {
            try {
                $count = $model::count();
                $total += $count;
                
                if ($count > 0) {
                    $this->info("✅ {$name}: {$count} записей");
                } else {
                    $this->warn("⚠️  {$name}: 0 записей (пусто)");
                }
            } catch (\Exception $e) {
                $this->error("❌ {$name}: Ошибка - " . $e->getMessage());
            }
        }
        
        $this->newLine();
        $this->info("📈 Всего записей: {$total}");
    }

    private function checkTable($tableName)
    {
        $models = [
            'best_offers' => Best_offer::class,
            'reviews' => Reviews::class,
            'news' => News::class,
            'partners' => Partner::class,
            'countries' => Countries_image::class,
            'employees' => Employee::class,
            'awards' => Award::class,
            'tours' => Tour::class,
            'bookings' => Booking::class,
            'users' => User::class,
        ];
        
        if (!isset($models[$tableName])) {
            $this->error("Таблица '{$tableName}' не найдена!");
            return;
        }
        
        $model = $models[$tableName];
        $count = $model::count();
        
        $this->info("📊 Таблица: {$tableName}");
        $this->info("Количество записей: {$count}");
        $this->newLine();
        
        if ($count > 0) {
            $items = $model::take(5)->get();
            $this->info("Первые 5 записей:");
            $this->table(
                $this->getTableHeaders($model),
                $items->map(function ($item) {
                    return $this->getTableRow($item);
                })->toArray()
            );
        }
    }

    private function getTableHeaders($model)
    {
        $instance = new $model();
        $fillable = $instance->getFillable();
        
        return array_slice(array_merge(['id'], $fillable), 0, 5);
    }

    private function getTableRow($item)
    {
        $row = ['id' => $item->id];
        
        $fillable = $item->getFillable();
        foreach (array_slice($fillable, 0, 4) as $field) {
            $value = $item->$field ?? null;
            if (is_string($value) && strlen($value) > 30) {
                $value = substr($value, 0, 30) . '...';
            }
            $row[$field] = $value ?? '-';
        }
        
        return $row;
    }
}
