<?php

 namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use App\Modules\Integrations\Services\ReplaceSnapshot;
use Yajra\DataTables\Facades\DataTables;

class GoogleSpreadsheetController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Teacher::select('id', 'teacher_name', 'teacher_code', 'status', 'type', 'subject', 'medium')->orderBy('id', 'ASC');
            return DataTables::of($data)
                ->addIndexColumn()
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $instance->where(function ($w) use ($request) {
                            $search = \App\Support\TableSearch::term($request);
                            $w->where('teacher_name', 'LIKE', "%$search%")->orWhere('teacher_code', 'LIKE', "%$search%");
                        });
                    }
                })
                ->make(true);
        }
        return view('admin.teacher.index');
    }

    public function sheetData()
    {
        $this->importSnapshot(app(SheetSnapshotReader::class)->read('TEACHER'));
        return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
    }

    public function importSnapshot(array $values): void
    {
        $records = [];
        foreach (SheetSnapshotReader::rows($values, 2) as $row) {
            $record = array_combine(['teacher_name', 'teacher_code', 'status', 'type', 'subject', 'medium'], array_slice(array_pad($row, 6, ''), 0, 6));
            SheetSnapshotReader::requireFields($record, ['teacher_name', 'teacher_code']);
            $records[] = $record;
        }
        app(ReplaceSnapshot::class)->reconcile(Teacher::class, $records, 'teacher_code', 'teacher_name');
    }
}
