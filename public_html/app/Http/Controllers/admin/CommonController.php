<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class CommonController extends Controller
{
    public function delete($route,$id)
    {   
        abort_unless(in_array($route, ['workhour', 'roles'], true) && \Illuminate\Support\Facades\Route::has($route.'.destroy'), 404);
        if (request()->ajax()) {
            return view('admin.layouts.delete')->with(compact('route','id'));
        }
    }
    public function maintenance_on(){
        Artisan::call('down');
    }
    public function maintenance_off(){
        Artisan::call('up');
    }
}
