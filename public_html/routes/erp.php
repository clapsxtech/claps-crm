<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Erp\WorkspaceController;
Route::get('/erp/from-sheets', [\App\Http\Controllers\Erp\SheetTransitionController::class,'index'])->middleware('auth')->name('erp.sheets');
Route::get('/teacher',[\App\Http\Controllers\Erp\TeacherController::class,'entry'])->middleware('auth')->name('teacher.portal');
Route::permanentRedirect('/erp', '/')->name('erp.compatibility');
Route::middleware(['auth', \App\Http\Middleware\TeamAuthorization::class])->prefix('erp')->name('erp.')->group(function () {
    Route::get('/my-tasks',[\App\Http\Controllers\Erp\OwnTasksController::class,'index'])->name('tasks.mine');
    Route::post('/my-tasks/{task}/complete',[\App\Http\Controllers\Erp\OwnTasksController::class,'complete'])->whereNumber('task')->name('tasks.complete');
    Route::post('/my-tasks/{task}/items/{item}/complete',[\App\Http\Controllers\Erp\OwnTasksController::class,'completeItem'])->whereNumber('task')->whereNumber('item')->name('tasks.item.complete');
    Route::post('/teacher/results',[\App\Http\Controllers\Erp\TeacherController::class,'result'])->name('teacher.result');
    Route::get('/teacher',[\App\Http\Controllers\Erp\TeacherController::class,'index'])->name('teacher.index');
    Route::post('/teacher/attendance',[\App\Http\Controllers\Erp\TeacherController::class,'submit'])->name('teacher.submit');
    Route::get('/units/{unit}/academics/export',[\App\Http\Controllers\Erp\AcademicsController::class,'export'])->whereNumber('unit')->name('academics.export');
    Route::get('/units/{unit}/students/{student}',[\App\Http\Controllers\Erp\StudentProfileController::class,'show'])->whereNumber('unit')->whereNumber('student')->name('student-profile');
    Route::get('/units/{unit}/business/{page?}',[\App\Http\Controllers\Erp\BusinessController::class,'page'])->whereNumber('unit')->name('business');
    Route::post('/units/{unit}/business-actions/{action}',[\App\Http\Controllers\Erp\BusinessController::class,'mutate'])->whereNumber('unit')->name('business.mutate');
    Route::get('/units/{unit}/exports/{dataset}',[\App\Http\Controllers\Erp\BusinessController::class,'export'])->whereNumber('unit')->name('export');
    Route::get('/units/{unit}/reconciliation',[\App\Http\Controllers\Erp\BusinessController::class,'reconciliation'])->whereNumber('unit')->name('reconciliation');
    Route::get('/units/{unit}/lookup',[\App\Http\Controllers\Erp\BusinessController::class,'lookup'])->whereNumber('unit')->name('lookup');
    Route::get('/units/{unit}/payment-requests/{id}',[\App\Http\Controllers\Erp\BusinessController::class,'requestPrint'])->whereNumber('unit')->whereNumber('id')->name('request.print');
    Route::get('/units/{unit}',[WorkspaceController::class,'workspace'])->whereNumber('unit')->name('workspace');
    Route::post('/units/{unit}/{action}',[WorkspaceController::class,'mutate'])->whereNumber('unit')->name('mutate');
    Route::get('/units/{unit}/receipts/{entry}',[WorkspaceController::class,'receipt'])->whereNumber('unit')->whereNumber('entry')->name('receipt');
});

Route::middleware(['auth','admin'])->prefix('erp/administration')->name('erp.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Erp\AdministrationController::class,'index'])->name('administration');
    Route::post('/teams', [\App\Http\Controllers\Erp\AdministrationController::class,'team'])->name('teams.create');
    Route::post('/teams/{id}', [\App\Http\Controllers\Erp\AdministrationController::class,'team'])->whereNumber('id')->name('teams.update');
    Route::post('/teams/{id}/archive', [\App\Http\Controllers\Erp\AdministrationController::class,'archive'])->whereNumber('id')->name('teams.archive');
    Route::post('/onboard', [\App\Http\Controllers\Erp\AdministrationController::class,'onboard'])->name('onboard');
    Route::post('/accounts/{id}', [\App\Http\Controllers\Erp\AdministrationController::class,'account'])->whereNumber('id')->middleware('throttle:20,1')->name('accounts.update');
    Route::get('/operations', [\App\Http\Controllers\Erp\AdministrationController::class,'operations'])->name('operations');
});

Route::middleware(['auth','admin'])->prefix('erp/migration-center')->name('erp.migration.')->group(function () {
    Route::get('/',[\App\Http\Controllers\Erp\MigrationCenterController::class,'index'])->name('center');
    Route::post('/preview',[\App\Http\Controllers\Erp\MigrationCenterController::class,'preview'])->middleware('throttle:10,1')->name('preview');
    Route::post('/execute',[\App\Http\Controllers\Erp\MigrationCenterController::class,'execute'])->middleware('throttle:5,1')->name('execute');
});

// Private records have their own explicit role check and must never enter teacher exports.
Route::middleware('auth')->prefix('erp/units/{unit}/tuition')->whereNumber('unit')->group(function () {
    Route::get('/', [\App\Http\Controllers\Erp\TuitionController::class,'index'])->name('erp.tuition');
    Route::get('/statement', [\App\Http\Controllers\Erp\TuitionController::class,'statement'])->name('erp.tuition.statement');
    Route::post('/{operation}', [\App\Http\Controllers\Erp\TuitionController::class,'mutate'])->name('erp.tuition.mutate');
});
