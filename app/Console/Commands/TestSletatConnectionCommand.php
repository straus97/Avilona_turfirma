<?php

namespace App\Console\Commands;

use App\Services\Sletat\SletatApiService;
use Illuminate\Console\Command;

class TestSletatConnectionCommand extends Command
{
    protected $signature = 'sletat:test';
    protected $description = 'Test connection to Sletat.ru API';

    public function handle()
    {
        $this->info('Testing connection to Sletat.ru API...');
        
        $sletatService = new SletatApiService();
        
        try {
            // Тестируем получение стран
            $this->info('Testing GetCountries...');
            $countries = $sletatService->getCountries();
            $this->info("✅ Successfully retrieved " . count($countries) . " countries");
            
            // Показываем первые 5 стран
            if (!empty($countries)) {
                $this->info('First 5 countries:');
                foreach (array_slice($countries, 0, 5) as $country) {
                    $this->line("- {$country['Name']} (ID: {$country['Id']})");
                }
            }
            
            // Тестируем получение городов вылета
            $this->info('Testing GetDepartCities...');
            $cities = $sletatService->getDepartCities();
            $this->info("✅ Successfully retrieved " . count($cities) . " departure cities");
            
            // Показываем первые 5 городов
            if (!empty($cities)) {
                $this->info('First 5 departure cities:');
                foreach (array_slice($cities, 0, 5) as $city) {
                    $this->line("- {$city['Name']} (ID: {$city['Id']})");
                }
            }
            
            $this->info('🎉 Sletat.ru API connection test completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ Connection test failed: ' . $e->getMessage());
            $this->error('Please check your Sletat.ru credentials in .env file:');
            $this->error('SLETAT_LOGIN=your_login');
            $this->error('SLETAT_PASSWORD=your_password');
            return 1;
        }
        
        return 0;
    }
}
