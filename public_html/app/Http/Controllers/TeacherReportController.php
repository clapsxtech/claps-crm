<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\TeacherReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use App\Modules\Integrations\Services\ReplaceSnapshot;
use Yajra\DataTables\Facades\DataTables;

class TeacherReportController extends Controller
{

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = TeacherReport::select('id','student_name','student_code','teacher_name','teacher_code','subject','date','date_submitted','chapter','topic','hour_taken','homework_status')->orderBy('date_submitted', 'desc');
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->filter(function ($instance) use ($request) {
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = \App\Support\TableSearch::term($request);
                                $w->where('student_name', 'LIKE', "%$search%")->orWhere('teacher_name', 'LIKE', "%$search%")->orWhere('student_code', 'LIKE', "%$search%")->orWhere('teacher_code', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->make(true);
        }
        return view('admin.report_teacherhour.index');
    }

    public function syncTeacherReportData()
    {
        $this->importSnapshot(app(SheetSnapshotReader::class)->read('WORKHOUR'));
        return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
    }

    public function importSnapshot(array $values): void
    {
        $records = [];
        foreach (SheetSnapshotReader::rows($values, 11) as $row) {
            $record = array_combine(['teacher_name', 'teacher_code', 'student_name', 'student_code', 'subject',
                'date', 'date_submitted', 'chapter', 'topic', 'hour_taken', 'homework_status'], array_slice($row, 0, 11));
            SheetSnapshotReader::requireFields($record, ['teacher_name', 'teacher_code', 'student_name', 'student_code', 'subject', 'date_submitted']);
            $record['date'] = SheetSnapshotReader::date($record['date']);
            $record['hour_taken'] = SheetSnapshotReader::decimal($record['hour_taken']);
            if (!in_array($record['homework_status'], ['yes', 'no'], true)) SheetSnapshotReader::invalid();
            $record['dob'] = null; // Legacy extra credential column is deliberately not imported.
            $records[] = $record;
        }
        app(ReplaceSnapshot::class)->replace(TeacherReport::class, $records);
    }

    /** Compatibility entry points share the hardened current attendance boundary. */
    public function attendanceReport(Request $request)
    {
        return app(WorkHourController::class)->attendanceReport($request);
    }

    public function getStudentData(Request $request)
    {
        return app(WorkHourController::class)->getStudentData($request);
    }

    public function studentsAttendanceReport(Request $request)
    {
        return app(WorkHourController::class)->studentsAttendanceReport($request);
    }

    public function getTeachersData(Request $request)
    {
        return app(WorkHourController::class)->getTeachersData($request);
    }
}
