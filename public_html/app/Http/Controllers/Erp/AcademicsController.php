<?php
namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Access, Reporting};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicsController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d',
            'through' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
        ]);
    }

    private function results(int $unit, array $filters)
    {
        $query = DB::table('erp_marks as m')
            ->join('erp_enrollments as e', function ($join) { $join->on('e.id','=','m.enrollment_id')->on('e.unit_id','=','m.unit_id'); })
            ->join('erp_students as s', function ($join) { $join->on('s.id','=','e.student_id')->on('s.unit_id','=','e.unit_id'); })
            ->join('erp_teachers as t','t.id','=','e.teacher_id')
            ->join('erp_teacher_units as pool', function ($join) { $join->on('pool.teacher_id','=','t.id')->on('pool.unit_id','=','e.unit_id'); })
            ->join('erp_subjects as subject','subject.id','=','e.subject_id')
            ->where('m.unit_id',$unit)
            ->select('m.id','m.assessment','m.assessed_on','m.score','m.maximum','m.notes','s.id as student_id','s.name as student_name','s.code as student_code','t.name as teacher_name','subject.name as subject_name');
        if (!empty($filters['q'])) $query->where(function ($query) use ($filters) {
            foreach (['s.name','s.code','t.name','subject.name','m.assessment'] as $field) $query->orWhere($field,'like','%'.$filters['q'].'%');
        });
        if (!empty($filters['from'])) $query->where('m.assessed_on','>=',$filters['from']);
        if (!empty($filters['through'])) $query->where('m.assessed_on','<=',$filters['through']);
        return $query->orderByDesc('m.assessed_on')->orderByDesc('m.id');
    }

    public function index(Request $request, int $unit)
    {
        (new Access())->unit($request->user(), $unit, false);
        $filters = $this->filters($request);
        $business = DB::table('erp_units')->find($unit);
        $results = $this->results($unit, $filters)->paginate(25, ['*'], 'results_page')->withQueryString();
        $eligibilities = DB::table('erp_eligibilities as e')
            ->join('erp_teacher_units as pool','pool.teacher_id','=','e.teacher_id')
            ->join('erp_teachers as t','t.id','=','e.teacher_id')
            ->join('erp_subjects as subject','subject.id','=','e.subject_id')
            ->where('pool.unit_id',$unit)
            ->select('e.grade','e.syllabus','t.name as teacher_name','subject.name as subject_name')
            ->orderBy('subject.name')->orderBy('t.name')->orderBy('e.id')
            ->paginate(25, ['*'], 'subjects_page')->withQueryString();
        return view('erp.academics', compact('business','results','eligibilities','filters'));
    }

    public function export(Request $request, int $unit)
    {
        (new Access())->unit($request->user(), $unit, false);
        $query = $this->results($unit, $this->filters($request));
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output','w');
            fputcsv($out, ['Date','Student code','Student','Teacher','Subject','Assessment','Score','Maximum','Percentage','Notes']);
            $reporting = new Reporting();
            foreach ($query->cursor() as $row) {
                $values = [$row->assessed_on,$row->student_code,$row->student_name,$row->teacher_name,$row->subject_name,$row->assessment,$row->score,$row->maximum,number_format(100*$row->score/max(1,$row->maximum),2,'.',''),$row->notes];
                fputcsv($out, array_map(fn($value) => $reporting->safeCsvValue($value),$values));
            }
            fclose($out);
        }, 'unit-'.$unit.'-assessment-results.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
