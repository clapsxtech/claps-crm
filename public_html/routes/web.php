<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\admin\CommonController;
use App\Http\Controllers\admin\RoleController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DueController;
use App\Http\Controllers\admin\GoogleSpreadsheetController;
use App\Http\Controllers\admin\MarklistController;
use App\Http\Controllers\admin\MasterController;
use App\Http\Controllers\admin\StudentsController;
use App\Http\Controllers\admin\SubjectController;
use App\Http\Controllers\admin\TopicController;
use App\Http\Controllers\MarkListFrontentController;
use App\Http\Controllers\admin\UsersController;
use App\Http\Controllers\admin\WorkHourReportController;
use App\Http\Controllers\WorkHourController;
use App\Http\Controllers\TeacherReportController;
use App\Http\Controllers\MarklistReportController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\TimeTableController;

Auth::routes();
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
Route::get('/timetableReport', [TimeTableController::class, 'timetableReport'])->name('timetableReport'); 
Route::get('/timetable', [TimeTableController::class, 'timetableReport'])->name('timetable'); 

Route::get('/get-teacher-dob', [TimeTableController::class, 'getTeacherDOB'])->name('get-teacher-dob');

Route::middleware('auth:web')->group(function () {
    Route::get('/student-attendance-report', [WorkHourController::class, 'studentsAttendanceReport'])->name('student-attendance-report');
    Route::get('/get-teachers', [WorkHourController::class, 'getTeachersData'])->name('get-teachers');
    Route::get('/get-teacher', [WorkHourController::class, 'getTeachers'])->name('get-teacher');
    Route::get('/get-students', [WorkHourController::class, 'getStudentData'])->name('get-students');
    Route::get('/get-student', [WorkHourController::class, 'getStudent'])->name('get-student');
    Route::get('/attendanceReport', [WorkHourController::class, 'attendanceReport'])->name('attendanceReport');
    Route::get('/get-students-mark', [MarklistReportController::class, 'getStudentData'])->name('get-students-mark');
    Route::get('/markreport', [MarklistReportController::class, 'MarklistReport'])->name('markreport');
});

Route::get('/attendance', [WorkHourController::class, 'frontetHourUploadCreate'])->name('attendance'); 
Route::get('studentinfom', [WorkHourController::class, 'getStudentinfo'])->name('studentinfom')->middleware('web');
Route::post('/upload-workhour', [WorkHourController::class, 'store'])->name('upload-workhour')->middleware('web');


Route::get('/resultform', [MarkListFrontentController::class, 'frontetMarkUploadCreate'])->name('resultform'); 
Route::get('studentinforesult', [MarkListFrontentController::class, 'getStudentinfo'])->name('studentinforesult')->middleware('web');
Route::post('/upload-result', [MarkListFrontentController::class, 'store'])->name('upload-result')->middleware('web');
Route::get('/performance', [PerformanceController::class, 'create'])->name('performenceform'); 
Route::post('/performnce-upload', [PerformanceController::class, 'store'])->name('performnce-upload')->middleware('web');



Route::post('/submit-application', [MarkListFrontentController::class, 'store'])->name('submit-application')->middleware('web');
Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('backend', 'App\Http\Controllers\Auth\LoginController@showLoginForm');
Route::prefix('admin')->group(function () {
Route::group(['middleware' => ['auth:web', 'admin']], function () {
        Route::post('/maintenance-on', [CommonController::class, 'maintenance_on'])->name('maintenance.on');
        Route::post('/maintenance-off', [CommonController::class, 'maintenance_off'])->name('maintenance.off');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/update-settings', [SettingsController::class, 'update']);
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('users', UsersController::class)->except(['show', 'destroy']);
        Route::get('delete/{route}/{id}', [CommonController::class, 'delete'])->name('delete');
        Route::resource('subjects', SubjectController::class)->except(['show', 'destroy']);
        Route::resource('topics', TopicController::class)->except(['show', 'destroy']);

        Route::get('profile', [UsersController::class, 'Profile'])->name('profile');
        Route::resource('teachers', GoogleSpreadsheetController::class)->only(['index']);
        Route::resource('students', StudentsController::class)->only(['index']);
        Route::resource('marklist', MarklistController::class)->except(['show', 'destroy']);
        Route::resource('master', MasterController::class)->only(['index']);
        Route::resource('monthlyreport', WorkHourReportController::class)->only(['index']);
        Route::resource('workhour', WorkHourController::class);
        Route::get('studentinfoedit', [WorkHourController::class, 'getStudentedit'])->name('studentinfoedit');

        Route::get('teacherinfo', [MarklistController::class, 'getTeacherInfo'])->name('teacherinfo');
        Route::get('studentinfo', [WorkHourController::class, 'getStudentinfo'])->name('studentinfo');
        Route::resource('workreport', TeacherReportController::class);
        Route::get('syncTeachers', [GoogleSpreadsheetController::class, 'sheetData'])->name('syncTeachers');
        Route::get('syncStudents', [StudentsController::class, 'sheetData'])->name('syncStudents');
        Route::get('syncMasters', [MasterController::class, 'MastersSynkdata'])->name('syncMasters');
        Route::get('syncTeacherReportData', [TeacherReportController::class, 'syncTeacherReportData'])->name('syncTeacherReportData');
        Route::resource('marklistreport', MarklistReportController::class);
        Route::get('syncMarklistReportData', [MarklistReportController::class, 'syncMarklistReportData'])->name('syncMarklistReportData');
        Route::get('syncWorkHoureReportData', [WorkHourController::class, 'syncWorkHoureReportData'])->name('syncWorkHoureReportData');
        Route::resource('duereport', DueController::class);
        Route::get('syncDueReportData', [DueController::class, 'syncDueReportData'])->name('syncDueReportData');
        Route::resource('timetable', TimeTableController::class);

        Route::get('syncTimeTableData', [TimeTableController::class, 'syncTimeTableData'])->name('syncTimeTableData');
        Route::get('studentTimetable', [TimeTableController::class, 'studentTimetable'])->name('studentTimetable');
        Route::get('work-hours/export', [WorkHourController::class, 'exportWorkHours'])->name('work_hours.export');


        
    });
});
