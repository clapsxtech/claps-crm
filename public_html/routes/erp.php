<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Erp\WorkspaceController;
Route::middleware('auth')->prefix('erp')->name('erp.')->group(function () {
    Route::get('/teacher',[\App\Http\Controllers\Erp\TeacherController::class,'index'])->name('teacher.index');
    Route::post('/teacher/attendance',[\App\Http\Controllers\Erp\TeacherController::class,'submit'])->name('teacher.submit');
    Route::get('/',[WorkspaceController::class,'index'])->name('index');
    Route::get('/units/{unit}',[WorkspaceController::class,'workspace'])->whereNumber('unit')->name('workspace');
    Route::post('/units/{unit}/{action}',[WorkspaceController::class,'mutate'])->whereNumber('unit')->name('mutate');
    Route::get('/units/{unit}/receipts/{entry}',[WorkspaceController::class,'receipt'])->whereNumber('unit')->whereNumber('entry')->name('receipt');
});
