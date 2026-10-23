<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Master;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use App\Modules\Integrations\Services\ReplaceSnapshot;
use Yajra\DataTables\Facades\DataTables;

class MasterController extends Controller
{

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Master::select('id','student_name','student_code','teacher_name','teacher_code','hour_payment','profit_per_hour','payment_per_hour');
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->filter(function ($instance) use ($request) {
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = \App\Support\TableSearch::term($request);
                                $w->where('student_name', 'LIKE', "%$search%")
                                    ->orWhere('student_code', 'LIKE', "%$search%")
                                    ->orWhere('teacher_name', 'LIKE', "%$search%")
                                    ->orWhere('teacher_code', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->make(true);
        }
        return view('admin.master.index');
    }

    public function MastersSynkdata()
    {
        $this->importSnapshot(app(SheetSnapshotReader::class)->read('MIXED'));
        return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
    }

    public function importSnapshot(array $values): void
    {
        $records = []; $teachers = []; $names = []; $students = []; $pairs = [];
        foreach (SheetSnapshotReader::rows($values, 8) as $row) {
            $record = array_combine(['student_code', 'student_name', 'teacher_code', 'teacher_name', 'teacher_dob',
                'hour_payment', 'profit_per_hour', 'payment_per_hour', 'team'], array_slice(array_pad($row, 9, ''), 0, 9));
            SheetSnapshotReader::requireFields($record, ['student_code', 'student_name', 'teacher_code', 'teacher_name', 'teacher_dob']);
            $record['hour_payment'] = SheetSnapshotReader::decimal($record['hour_payment']);
            $record['payment_per_hour'] = SheetSnapshotReader::decimal($record['payment_per_hour']);
            $record['profit_per_hour'] = SheetSnapshotReader::decimal($record['profit_per_hour'], true);
            $teacher = [$record['teacher_name'], $record['teacher_dob']];
            $teacherCode = $record['teacher_code']; $studentCode = $record['student_code'];
            $name = mb_strtolower($record['teacher_name']);
            if (isset($teachers[$teacherCode]) && $teachers[$teacherCode] !== $teacher) SheetSnapshotReader::invalid();
            if (isset($names[$name]) && $names[$name] !== $teacherCode) SheetSnapshotReader::invalid();
            if (isset($students[$studentCode]) && $students[$studentCode] !== $record['student_name']) SheetSnapshotReader::invalid();
            $teachers[$teacherCode] = $teacher; $names[$name] = $teacherCode; $students[$studentCode] = $record['student_name'];
            $key = json_encode([$teacherCode, $studentCode]);
            if (isset($pairs[$key]) && $pairs[$key] !== $record) SheetSnapshotReader::invalid();
            $pairs[$key] = $record;
        }
        app(ReplaceSnapshot::class)->replace(Master::class, array_values($pairs));
    }
}
