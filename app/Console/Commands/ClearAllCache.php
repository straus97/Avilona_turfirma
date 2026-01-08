<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ClearAllCache extends Command
{
    protected $signature = 'cache:clear-all';
    protected $description = 'Очистить весь кэш приложения';

    public function handle()
    {
        $this->info('🧹 Очистка всех кэшей...');
        $this->newLine();
        
        // Кэш приложения
        $this->info('Очистка кэша приложения...');
        Cache::flush();
        Artisan::call('cache:clear');
        $this->info('✅ Кэш приложения очищен');
        
        // Кэш конфигурации
        $this->info('Очистка кэша конфигурации...');
        Artisan::call('config:clear');
        $this->info('✅ Кэш конфигурации очищен');
        
        // Кэш маршрутов
        $this->info('Очистка кэша маршрутов...');
        Artisan::call('route:clear');
        $this->info('✅ Кэш маршрутов очищен');
        
        // Кэш представлений
        $this->info('Очистка кэша представлений...');
        Artisan::call('view:clear');
        $this->info('✅ Кэш представлений очищен');
        
        // Compiled classes
        $this->info('Очистка compiled classes...');
        Artisan::call('clear-compiled');
        $this->info('✅ Compiled classes очищены');
        
        $this->newLine();
        $this->info('🎉 Весь кэш успешно очищен!');
        
        return 0;
    }
}
