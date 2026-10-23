<?php

namespace App\Http\Controllers\admin;
use App\Models\Students;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use App\Modules\Integrations\Services\ReplaceSnapshot;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class StudentsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Students::select('id', 'student_name', 'student_code', 'status', 'hour_rate', 'category', 'source', 'hours')->orderBy('id', 'ASC');
            return DataTables::of($data)
                ->addIndexColumn()
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $instance->where(function ($w) use ($request) {
                            $search = \App\Support\TableSearch::term($request);
                            $w->where('student_name', 'LIKE', "%$search%")->orWhere('student_code', 'LIKE', "%$search%");
                        });
                    }
                })
                ->make(true);
        }
        return view('admin.students.index');
    }

    public function sheetData()
    {
        $this->importSnapshot(app(SheetSnapshotReader::class)->read('STUDENT'));
        return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
    }

    public function importSnapshot(array $values): void
    {
        $records = [];
        foreach (SheetSnapshotReader::rows($values, 4) as $row) {
            $row = array_pad($row, 8, '');
            $record = ['student_name' => $row[1], 'status' => $row[2], 'student_code' => $row[3],
                'hour_rate' => $row[4] === '' ? '' : SheetSnapshotReader::decimal($row[4]), 'category' => $row[5],
                'source' => $row[6], 'hours' => $row[7] === '' ? '' : SheetSnapshotReader::decimal($row[7])];
            SheetSnapshotReader::requireFields($record, ['student_name', 'student_code']);
            $records[] = $record;
        }
        app(ReplaceSnapshot::class)->reconcile(Students::class, $records, 'student_code', 'student_name');
    }
}
