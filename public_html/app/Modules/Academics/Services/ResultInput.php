<?php

namespace App\Modules\Academics\Services;

use App\Models\Students;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResultInput
{
    public function marklist(array $input, bool $admin = false, bool $requireKey = true, bool $resolveReferences = true): array
    {
        $rules = [
            'teacher_name' => $admin ? 'required|integer'.($resolveReferences ? '|exists:teachers,id' : '') : 'required|string|max:255',
            'teacher_code' => 'required|string|max:255',
            'student_name' => $admin ? 'required|integer'.($resolveReferences ? '|exists:students,id' : '') : 'required|string|max:255',
            'subject' => 'required|integer'.($resolveReferences ? '|exists:subjects,id' : ''),
            'chapter' => 'required|string|max:255',
            'total_mark' => 'required|numeric|gt:0|max:1000000000',
            'mark_scored' => 'required|numeric|min:0|lte:total_mark',
            'examination_date' => 'required|date_format:Y-m-d',
            'mode_of_exam' => 'required|string|in:Viva (live),Written (live),written (offline),written + Viva(live)',
            'remark' => 'nullable|string|max:255',
        ];
        if (!$admin) {
            $rules['student_code'] = 'required|string|max:255';
        }
        if ($requireKey) {
            $rules['submission_key'] = 'required|uuid';
        }
        $validated = Validator::make($input, $rules, [
            'submission_key.required' => 'A submission key is required. Reload the form before submitting.',
        ])->validate();

        // Normalize the allowlist before hashing. Never include a credential.
        $data = [
            'teacher_name' => (string) $validated['teacher_name'],
            'teacher_code' => $validated['teacher_code'],
            'student_name' => (string) $validated['student_name'],
            'student_code' => $validated['student_code'] ?? null,
            'subject' => (int) $validated['subject'],
            'chapter' => $validated['chapter'],
            'total_mark' => (string) (float) $validated['total_mark'],
            'mark_scored' => (string) (float) $validated['mark_scored'],
            'examination_date' => $validated['examination_date'],
            'mode_of_exam' => $validated['mode_of_exam'],
            'remark' => $validated['remark'] ?? null,
        ];
        $names = [
            'teacher_name' => $data['teacher_name'],
            'student_name' => $data['student_name'],
            'subject' => $resolveReferences ? Subject::findOrFail($data['subject'])->name : null,
        ];
        if ($admin && $resolveReferences) {
            // Legacy admin forms use IDs in *_name fields. Public forms use
            // names. Resolve them at the boundary; persist immutable names/codes, not
            // snapshot row IDs that could be reassigned by a later import.
            $teacher = Teacher::findOrFail($data['teacher_name']);
            $student = Students::findOrFail($data['student_name']);
            Validator::make([
                'teacher_name' => $teacher->teacher_name,
                'student_name' => $student->student_name,
                'student_code' => $student->student_code,
            ], [
                'teacher_name' => 'required|string|max:255',
                'student_name' => 'required|string|max:255',
                'student_code' => 'required|string|max:255',
            ], ['student_code.required' => 'The selected student needs a stable student code before a result can be saved.'])->validate();
            if ((string) $teacher->teacher_code !== $data['teacher_code']) {
                throw ValidationException::withMessages(['teacher_code' => 'The teacher and teacher code do not match.']);
            }
            $data['teacher_name'] = $teacher->teacher_name;
            $data['student_name'] = $student->student_name;
            $data['student_code'] = $student->student_code;
            $names['teacher_name'] = $teacher->teacher_name;
            $names['student_name'] = $student->student_name;
        }

        return [$data, $names];
    }
}
