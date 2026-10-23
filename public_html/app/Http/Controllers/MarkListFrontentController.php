<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Subject;
use App\Modules\Academics\Actions\SubmitMarklist;
use App\Modules\Academics\Services\ResultResponse;
use App\Modules\People\Services\TeacherIdentityVerifier;
use Illuminate\Http\Request;

class MarkListFrontentController extends Controller
{
    public function getStudentinfo(Request $request, TeacherIdentityVerifier $identity)
    {
        $teacher = $identity->verifyTeacher($request->all());
        $students = Master::where('teacher_name', $teacher->teacher_name)
            ->where('teacher_code', $teacher->teacher_code)
            ->get(['id', 'student_name', 'student_code']);

        return response()->json(['students' => $students, 'teacher_code' => $teacher->teacher_code]);
    }

    public function store(Request $request, SubmitMarklist $submit, ResultResponse $response)
    {
        return $response->submit(function () use ($submit, $request) {
            return $submit->publicSubmission($request->all());
        });
    }

    public function frontetMarkUploadCreate()
    {
        $teachers = Master::select('teacher_name')->distinct()->orderBy('teacher_name')->get();
        $subject = Subject::select('id', 'name')->get();

        return view('marklistform', compact('teachers', 'subject'));
    }
}
