<?php

namespace App\Console\Commands;

use App\Http\Controllers\admin\GoogleSpreadsheetController;
use App\Http\Controllers\admin\StudentsController;
use App\Http\Controllers\TeacherReportController;
use App\Http\Controllers\admin\MasterController;
use App\Http\Controllers\MarklistReportController;
use App\Http\Controllers\TimeTableController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\admin\GetStudentsController;


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
        Log::info('fetch:sheet-data command executed at ' . now());
    
        try {
        
        $MasterController = app(MasterController::class);
        // $TeacherReportController = app(TeacherReportController::class);
        $MarklistReportController = app(MarklistReportController::class);
        $Timetample = app(TimeTableController::class);
        // $DueController = app(DueController::class);
        // $GetStudentSynkdata=app(GetStudentsController::class);


        // $GetStudentSynkdata->GetStudentSynkdata();
        $MasterController->MastersSynkdata();
        // $DueController->syncDueReportData();
        $Timetample->syncTimeTableData();
        $MarklistReportController->syncMarklistReportData();

            Log::info('Scheduled task completed at: ' . now());
        } catch (\Exception $e) {
            Log::error('Error in scheduled task: ' . $e->getMessage());
        }
    }
}
