<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\admin\CommonController;
use App\Http\Controllers\admin\GetStudentsController;
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
Route::get('/student-attendance-report', [WorkHourController::class, 'studentsAttendanceReport'])->name('student-attendance-report'); 
Route::get('/get-teachers', [WorkHourController::class, 'getTeachersData'])->name('get-teachers');
Route::get('/get-teacher', [WorkHourController::class, 'getTeachers'])->name('get-teacher');
Route::get('/timetableReport', [TimeTableController::class, 'timetableReport'])->name('timetableReport'); 
Route::get('/timetable', [TimeTableController::class, 'timetable'])->name('timetable'); 

// Route::get('/', 'MarkController@index')->name('index');
Route::get('/get-teacher-dob', [TimeTableController::class, 'getTeacherDOB'])->name('get-teacher-dob');

Route::get('/get-students', [WorkHourController::class, 'getStudentData'])->name('get-students');
Route::get('/get-student', [WorkHourController::class, 'getStudent'])->name('get-student');

Route::get('/attendanceReport', [WorkHourController::class, 'attendanceReport'])->name('attendanceReport'); 

Route::get('/get-students-mark', [MarklistReportController::class, 'getStudentData'])->name('get-students-mark');
Route::get('/markreport', [MarklistReportController::class, 'MarklistReport'])->name('markreport'); 

Route::get('/attendance', [WorkHourController::class, 'frontetHourUploadCreate'])->name('attendance'); 
Route::get('studentinfom', [WorkHourController::class, 'getStudentinfo'])->name('studentinfom')->middleware('web');
Route::post('/upload-workhour', [WorkHourController::class, 'store'])->name('upload-workhour')->middleware('web');


Route::get('/resultform', [MarkListFrontentController::class, 'frontetMarkUploadCreate'])->name('resultform'); 
Route::get('studentinforesult', [MarkListFrontentController::class, 'getStudentinfo'])->name('studentinforesult')->middleware('web');
Route::post('/upload-result', [MarkListFrontentController::class, 'store'])->name('upload-result')->middleware('web');
Route::get('/performance', [PerformanceController::class, 'create'])->name('performenceform'); 
Route::post('/performnce-upload', [PerformanceController::class, 'store'])->name('performnce-upload')->middleware('web');



Route::post('/submit-application', [MarkListFrontentController::class, 'store'])->name('submit-application')->middleware('web');
Route::get('teacherinfofrontent', [MarkListFrontentController::class, 'getTeacherInfo'])->name('teacherinfofrontent')->middleware('web');
Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('backend', 'App\Http\Controllers\Auth\LoginController@showLoginForm');
Route::get('/maintenance-on', [CommonController::class, 'maintenance_on']);
Route::get('/maintenance-off', [CommonController::class, 'maintenance_off']);
Route::prefix('admin')->group(function () {
Route::group(['middleware' => 'auth:web'], function () {
        Route::get('/settings', [HomeController::class, 'index']);
        Route::post('/update-settings', [SettingsController::class, 'update']);
        Route::resource('roles', RoleController::class);
        Route::resource('users', UsersController::class);
        Route::get('delete/{route}/{id}', [CommonController::class, 'delete'])->name('delete');
        Route::resource('subjects', SubjectController::class);
        Route::resource('topics', TopicController::class);

        Route::get('profile', [UsersController::class, 'Profile'])->name('profile');
        Route::put('ProfileUpdate/{id}', [UsersController::class, 'ProfileUpdate'])->name('ProfileUpdate');
        Route::put('PasswordUpdate/{id}', [UsersController::class, 'PasswordUpdate'])->name('PasswordUpdate');
        Route::resource('teachers', GoogleSpreadsheetController::class);
        Route::resource('students', StudentsController::class);
        Route::resource('marklist', MarklistController::class);
        Route::resource('master', MasterController::class);
        Route::resource('getstudent', GetStudentsController::class);
        Route::resource('monthlyreport', WorkHourReportController::class);
        Route::resource('workhour', WorkHourController::class);
        Route::get('studentinfoedit', [WorkHourController::class, 'getStudentedit'])->name('studentinfoedit');

        Route::get('teacherinfo', [MarklistController::class, 'getTeacherInfo'])->name('teacherinfo');
        Route::get('studentinfo', [WorkHourController::class, 'getStudentinfo'])->name('studentinfo');
        Route::resource('workreport', TeacherReportController::class);
        Route::get('syncTeachers', [GoogleSpreadsheetController::class, 'sheetData'])->name('syncTeachers');
        Route::get('syncStudents', [StudentsController::class, 'sheetData'])->name('syncStudents');
        Route::get('syncMasters', [MasterController::class, 'MastersSynkdata'])->name('syncMasters');
        Route::get('syncGetStudent', [GetStudentsController::class, 'GetStudentSynkdata'])->name('syncGetStudent');
        Route::get('syncTeacherReportData', [TeacherReportController::class, 'syncTeacherReportData'])->name('syncTeacherReportData');
        Route::get('checkTeacherCodeDOB', [WorkHourController::class, 'checkTeacherCodeDOB'])->name('checkTeacherCodeDOB');
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
