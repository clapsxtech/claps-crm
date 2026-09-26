<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\GetStudent;
use App\Models\Subject;
use App\Models\WorkHour;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
                // ->addColumn('balance', function ($row) {
                //     return $row->total - $row->total_amount;
                // })
                ->addColumn('action', function ($row) {
                    $button = '<button type="button" data-href="' . route('subjects.edit', $row->id) . '" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                    $button .= '<button data-href="' . route('delete', ['subjects', $row->id]) . '" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                    return $button;
                })
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $search = $request->get('search');
                        $instance->where(function ($query) use ($search) {
                            $query->orWhere('get_students.student_name', 'LIKE', "%$search%")
                                ->orWhere('get_students.student_code', 'LIKE', "%$search%");
                        });
                    }
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('admin.report.index');
    }
}
