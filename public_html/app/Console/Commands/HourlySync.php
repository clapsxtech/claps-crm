<?php

namespace App\Console\Commands;

use App\Http\Controllers\TimeTableController;
use App\Http\Controllers\WorkHourController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HourlySync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:attendance-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        Log::info('fetch:attendance-data command executed at ' . now());
    
        // try {
        
        // $TeacherReportController = app(WorkHourController::class);
        // $Timetample = app(TimeTableController::class);

        // $TeacherReportController->syncWorkHoureReportData();
        // $Timetample->syncTimeTableData();

        //     Log::info('Scheduled task completed at: ' . now());
        // } catch (\Exception $e) {
        //     Log::error('Error in scheduled task: ' . $e->getMessage());
        // }
    }
}
