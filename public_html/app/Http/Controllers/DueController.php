<?php

namespace App\Http\Controllers;

use App\Models\DueReport;
use Carbon\Carbon;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use App\Modules\Integrations\Services\ReplaceSnapshot;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DueController extends Controller
{
    public function index(Request $request)
    {
        $student_name = DueReport::select('student_name')->distinct()->get();
        $status = DueReport::select('current_status')->distinct()->get();
        $section = DueReport::select('section')->distinct()->get();
        $admin_staff = DueReport::select('admin_staff')->distinct()->get();


        if ($request->ajax()) {
            $data = DueReport::select(
            'id',
            'student_name',
            'student_code',
            'phone',
            'status',
            'hour_rate',
            'monthly',
            'advance_expected',
            'section',
            'admin_staff',
            'last_month_balance',
            'recived',
            'total_advance',
            'hour_taken',
            'c_amount',
            'error',
            'balance',
            'current_status');
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->filter(function ($instance) use ($request) {
                        if ($request->get('student_name') && $request->get('student_name') != 'all') {
                            $instance->where('student_name', $request->get('student_name'));
                        }
                        if ($request->get('admin_staff') && $request->get('admin_staff') != 'all') {
                            $instance->where('admin_staff', $request->get('admin_staff'));
                        }
                        if ($request->get('status') && $request->get('status') != 'all') {
                            $instance->where('current_status', $request->get('status'));
                        }
                         if ($request->get('section') && $request->get('section') != 'all') {
                            $instance->where('section', $request->get('section'));
                        }
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = \App\Support\TableSearch::term($request);
                                $w->where('student_name', 'LIKE', "%$search%")
                                    ->orWhere('student_code', 'LIKE', "%$search%")
                                    ->orWhere('admin_staff', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->make(true);
        }
        return view('admin.duereport.index')->with(compact('student_name','status','admin_staff','section'));
    }

    public function syncDueReportData()
    {
        $this->importSnapshot(app(SheetSnapshotReader::class)->read('Due'));
        return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
    }

    public function importSnapshot(array $values): void
    {
        $records = [];
        $columns = ['student_name' => 0, 'student_code' => 1, 'phone' => 2, 'status' => 3,
            'hour_rate' => 4, 'monthly' => 6, 'advance_expected' => 9, 'section' => 10,
            'admin_staff' => 11, 'last_month_balance' => 12, 'recived' => 13, 'total_advance' => 14,
            'hour_taken' => 15, 'c_amount' => 16, 'error' => 17, 'balance' => 18, 'current_status' => 19];
        foreach (SheetSnapshotReader::rows($values, 2) as $row) {
            $row = array_pad($row, 20, '');
            $record = [];
            foreach ($columns as $column => $index) $record[$column] = $row[$index];
            SheetSnapshotReader::requireFields($record, ['student_name', 'student_code']);
            $records[] = $record;
        }
        app(ReplaceSnapshot::class)->replace(DueReport::class, $records);
    }
}
