<?php

namespace App\Console;

use App\Http\Controllers\admin\StudentsController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\admin\GoogleSpreadsheetController;
use Illuminate\Support\Facades\Log;
use App\App\Console\Commands\DailyQuote;
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // DailyQuote::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
      protected function schedule(Schedule $schedule)
{
    $schedule->command('fetch:sheet-data')->cron('*/15 * * * *');
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
