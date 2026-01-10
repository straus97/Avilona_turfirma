<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Отправка напоминаний о поездках каждый день в 10:00
        $schedule->command('bookings:send-trip-reminders')->dailyAt('10:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        \App\Console\Commands\GenerateSlugForDestinations::class,
        \App\Console\Commands\GenerateSlugForArticles::class,
        \App\Console\Commands\GenerateSlugForNews::class,
        \App\Console\Commands\GenerateSlugForClients::class,
        \App\Console\Commands\GenerateSlugForCountriesImages::class,
    ];
}
