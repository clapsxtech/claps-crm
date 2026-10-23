<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Marklist;
use App\Models\Students;
use App\Models\Subject;
use App\Models\Teacher;
use App\Modules\Academics\Actions\SubmitMarklist;
use App\Modules\Academics\Services\ResultInput;
use App\Modules\Academics\Services\ResultResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MarklistController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            // Public legacy rows store names; admin rows store string IDs.
            // Resolve only matching IDs/codes and retain names when no ID exists.
            $data = Marklist::query()
                ->select('marklists.*', DB::raw('COALESCE(teachers.teacher_name, marklists.teacher_name) as resolved_teacher_name'),
                    DB::raw('COALESCE(students.student_name, marklists.student_name) as resolved_student_name'), 'subjects.name as subject_name')
                ->leftJoin('subjects', 'marklists.subject', '=', 'subjects.id')
                ->leftJoin('students', function ($join) {
                    $join->on('marklists.student_name', '=', DB::raw('CAST(students.id AS CHAR)'))
                        ->on('marklists.student_code', '=', 'students.student_code');
                })
                ->leftJoin('teachers', function ($join) {
                    $join->on('marklists.teacher_name', '=', DB::raw('CAST(teachers.id AS CHAR)'))->on('marklists.teacher_code', '=', 'teachers.teacher_code');
                })
                ->orderByDesc('marklists.updated_at');

            $table = DataTables::of($data);
            foreach ([
                'teacher_name' => 'COALESCE(teachers.teacher_name, marklists.teacher_name)',
                'student_name' => 'COALESCE(students.student_name, marklists.student_name)',
                'teacher_code' => 'marklists.teacher_code',
                'subject_name' => 'subjects.name',
            ] as $column => $expression) {
                $table->filterColumn($column, function ($query, $keyword) use ($expression) { $query->whereRaw($expression.' LIKE ?', ['%'.$keyword.'%']); });
                $table->orderColumn($column, function ($query, $order) use ($expression) { $query->orderByRaw($expression.' '.(strtolower($order) === 'desc' ? 'DESC' : 'ASC')); });
            }
            return $table
                // Distinct SQL aliases keep the derived count query valid on MySQL/MariaDB.
                ->editColumn('teacher_name', function ($row) { return $row->resolved_teacher_name; })
                ->editColumn('student_name', function ($row) { return $row->resolved_student_name; })
                ->removeColumn('resolved_teacher_name')
                ->removeColumn('resolved_student_name')
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '<button type="button" data-href="'.e(route('marklist.edit', $row->id)).'" data-keyboard="false" data-backdrop="static" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>';
                })
                ->rawColumns(['action'])->make(true);
        }

        return view('admin.marklist.index');
    }

    public function create()
    {
        $students = Students::select('id', 'student_name')->where('status', 'OnGoing ✅')->orderBy('student_name')->get();
        $teachers = Teacher::select('id', 'teacher_name', 'teacher_code')->where('status', 'Available✅')->orderBy('teacher_name')->get();
        $subject = Subject::select('id', 'name')->get();

        return view('admin.marklist.create', compact('subject', 'students', 'teachers'));
    }

    public function getTeacherInfo(Request $request)
    {
        $teacher = Teacher::find($request->input('teacher_id'));

        return $teacher
            ? response()->json(['teacher_code' => $teacher->teacher_code])
            : response()->json(['success' => false, 'msg' => 'Teacher not found.'], 404);
    }

    public function store(Request $request, SubmitMarklist $submit, ResultResponse $response)
    {
        return $response->submit(function () use ($submit, $request) {
            return $submit->adminSubmission($request->all());
        });
    }

    public function store1(Request $request, SubmitMarklist $submit, ResultResponse $response)
    {
        return $this->store($request, $submit, $response);
    }

    public function edit($id)
    {
        $marklist = Marklist::findOrFail($id);
        // Include inactive historical choices so a record remains editable.
        $students = Students::select('id', 'student_name', 'student_code')->orderBy('student_name')->get();
        $teachers = Teacher::select('id', 'teacher_name', 'teacher_code')->orderBy('teacher_name')->get();
        $subject = Subject::select('id', 'name')->get();
        $selectedTeacher = $teachers->first(function ($teacher) use ($marklist) {
            return (string) $teacher->teacher_code === (string) $marklist->teacher_code
                && ((string) $teacher->id === (string) $marklist->teacher_name || $teacher->teacher_name === $marklist->teacher_name);
        });
        $selectedStudent = $students->first(function ($student) use ($marklist) {
            return ((string) $student->id === (string) $marklist->student_name || $student->student_name === $marklist->student_name)
                && $marklist->student_code && (string) $student->student_code === (string) $marklist->student_code;
        });

        return view('admin.marklist.edit', compact('marklist', 'students', 'teachers', 'subject', 'selectedTeacher', 'selectedStudent'));
    }

    public function update(Request $request, $id, ResultInput $input)
    {
        $marklist = Marklist::findOrFail($id);
        [$data] = $input->marklist($request->all(), true, false);
        $marklist->fill($data)->save();

        return response()->json(['success' => true, 'msg' => 'Successfully updated locally. Existing sheet exports are not changed.']);
    }

    public function destroy($id)
    {
        Marklist::findOrFail($id)->delete();

        return response()->json(['success' => true, 'msg' => 'Successfully deleted locally.']);
    }
}

