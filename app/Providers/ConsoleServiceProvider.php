<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use App\Console\Commands\FetchExchangeRate;

class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register any console commands.
     *
     * @return void
     */
    public function register()
    {
        // Register the FetchExchangeRate command
        $this->commands([
            FetchExchangeRate::class,
        ]);
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(Schedule $schedule)
    {
        // Schedule the command to run every 4 minutes
        $schedule->command('app:fetch-exchange-rate')->everyFourMinutes();
    }
}
