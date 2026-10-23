<?php

namespace App\Console\Commands;

use App\Http\Controllers\admin\MasterController;
use App\Http\Controllers\MarklistReportController;
use App\Http\Controllers\TimeTableController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DailyQuote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:sheet-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize master, timetable, and mark-list data from Google Sheets';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!config('integrations.sheets.enabled', false)) {
            $this->warn('Spreadsheet synchronization is disabled.');
            return self::SUCCESS;
        }
        Log::info('Google Sheets synchronization started.', ['started_at' => now()]);

        try {
            app(MasterController::class)->MastersSynkdata();
            app(TimeTableController::class)->syncTimeTableData();
            app(MarklistReportController::class)->syncMarklistReportData();

            Log::info('Google Sheets synchronization completed.', ['completed_at' => now()]);
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Spreadsheet synchronization failed.', ['exception_type' => get_class($e)]);
            return self::FAILURE;
        }
    }
}
