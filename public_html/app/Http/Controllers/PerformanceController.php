<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Topic;
use App\Modules\Academics\Actions\SubmitPerformance;
use App\Modules\Academics\Services\ResultResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function create()
    {
        $teachers = Master::select('teacher_name')->distinct()->orderBy('teacher_name')->get();
        $topics = Topic::select('id', 'name')->get();

        return view('performenceform', compact('teachers', 'topics'));
    }

    public function store(Request $request, SubmitPerformance $submit, ResultResponse $response)
    {
        return $response->submit(function () use ($submit, $request) {
            return $submit->execute($request->all());
        });
    }
}
