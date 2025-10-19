<?php

namespace Database\Seeders;

use App\Models\Tour;
use Illuminate\Database\Seeder;

class TourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Создание тестовых туров...');
        
        // Создаем обычные туры
        Tour::factory(30)->create();
        $this->command->info('✓ Создано 30 обычных туров');
        
        // Создаем горящие туры
        Tour::factory(10)->hotDeal()->create();
        $this->command->info('✓ Создано 10 горящих туров');
        
        // Создаем премиум туры
        Tour::factory(5)->premium()->create();
        $this->command->info('✓ Создано 5 премиум туров');
        
        // Создаем бюджетные туры
        Tour::factory(5)->budget()->create();
        $this->command->info('✓ Создано 5 бюджетных туров');
        
        $this->command->info('');
        $this->command->info('Всего создано: ' . Tour::count() . ' туров');
        $this->command->info('Активных туров: ' . Tour::active()->count());
        $this->command->info('Горящих предложений: ' . Tour::hotDeals()->count());
    }
}
