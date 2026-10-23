<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\GetStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class WorkHourReportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $data = GetStudent::select('get_students.id', 'get_students.student_name', 'get_students.student_code', 'get_students.total')
                ->leftJoin('work_hours', function ($join) {
                    $join->on('work_hours.student_code', '=', 'get_students.student_code');
                })
                ->groupBy('get_students.id')
                ->select('get_students.*', DB::raw('SUM(work_hours.total_amount) as total_amount'), DB::raw('SUM(work_hours.hour_taken) as hour_taken'));
                if (!empty($request->get('start_date'))) {
                    $data->whereDate('work_hours.date', '>=', $request->get('start_date'));
                }
                if (!empty($request->get('end_date'))) {
                    $data->whereDate('work_hours.date', '<=', $request->get('end_date'));
                }

                return DataTables::of($data)
                ->addIndexColumn()
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $search = \App\Support\TableSearch::term($request);
                        $instance->where(function ($query) use ($search) {
                            $query->orWhere('get_students.student_name', 'LIKE', "%$search%")
                                ->orWhere('get_students.student_code', 'LIKE', "%$search%");
                        });
                    }
                })

                ->make(true);
        }
        return view('admin.report.index');
    }
}
