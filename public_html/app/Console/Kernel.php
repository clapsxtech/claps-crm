<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (config('sheet_transition.enabled', false)) {
            $schedule->command('erp:sheets-refresh all --publish')->everyFifteenMinutes()->withoutOverlapping(30);
            $schedule->command('erp:sheets-prune')->daily()->withoutOverlapping();
        }
        if (config('integrations.sheets.enabled', false) && !config('sheet_transition.enabled') && config('sheet_transition.authority') !== 'sheets') {
            $schedule->command('fetch:sheet-data')->everyFifteenMinutes()->withoutOverlapping();
            $schedule->command('integration:deliver-outbox --limit=100')->everyMinute()->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
