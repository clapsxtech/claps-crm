<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Erp\WorkspaceController;
Route::middleware('auth')->prefix('erp')->name('erp.')->group(function () {
    Route::get('/my-tasks',[\App\Http\Controllers\Erp\OwnTasksController::class,'index'])->name('tasks.mine');
    Route::post('/my-tasks/{task}/complete',[\App\Http\Controllers\Erp\OwnTasksController::class,'complete'])->whereNumber('task')->name('tasks.complete');
    Route::post('/my-tasks/{task}/items/{item}/complete',[\App\Http\Controllers\Erp\OwnTasksController::class,'completeItem'])->whereNumber('task')->whereNumber('item')->name('tasks.item.complete');
    Route::get('/teacher',[\App\Http\Controllers\Erp\TeacherController::class,'index'])->name('teacher.index');
    Route::post('/teacher/attendance',[\App\Http\Controllers\Erp\TeacherController::class,'submit'])->name('teacher.submit');
    Route::get('/',[WorkspaceController::class,'index'])->name('index');
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
