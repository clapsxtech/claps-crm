<?php

namespace App\Modules\IdentityAccess\Services;

use App\Modules\People\Services\TeacherIdentityVerifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportAccess
{
    private $siteAccess;
    private $identities;

    public function __construct(SiteAccessPolicy $siteAccess, TeacherIdentityVerifier $identities)
    {
        $this->siteAccess = $siteAccess;
        $this->identities = $identities;
    }

    /** Scope before DataTables counts, filtering or pagination can expose rows. */
    public function scopeTeacherReport(Builder $query, Request $request): Builder
    {
        $this->siteAccess->assertSiteMember($request->user());
        $request->validate([
            'teacher_name' => ['nullable', 'string', 'max:255'],
            'teacher_code' => ['nullable', 'string', 'max:255'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'student_code' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->siteAccess->isAdministrator($request->user())) {
            if ($request->filled('teacher_name') && $request->input('teacher_name') !== 'all') {
                $query->where('teacher_name', $request->input('teacher_name'));
            }
            if ($request->filled('teacher_code')) {
                $query->where('teacher_code', $request->input('teacher_code'));
            }
        } else {
            $teacher = $this->identities->verifyTeacher($request->all());
            $query->where('teacher_name', $teacher->teacher_name)
                ->where('teacher_code', $teacher->teacher_code);
        }

        if ($request->filled('student_name') && $request->input('student_name') !== 'all') {
            $query->where('student_name', $request->input('student_name'));
        }
        if ($request->filled('student_code')) {
            $query->where('student_code', $request->input('student_code'));
        }

        return $query;
    }
}
