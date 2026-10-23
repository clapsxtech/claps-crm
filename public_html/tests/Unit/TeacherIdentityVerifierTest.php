<?php

namespace Tests\Unit;

use App\Modules\People\Services\TeacherIdentityVerifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeacherIdentityVerifierTest extends TestCase
{
    private function pair(array $override = []): array
    {
        return array_merge([
            'teacher_name' => 'QA Teacher', 'teacher_code' => 'T-QA',
            'student_name' => 'QA Student', 'student_code' => 'S-QA',
            'password' => '01/01/1990',
        ], $override);
    }

    public function test_verified_pair_returns_canonical_master_without_serialized_credential(): void
    {
        $master = $this->createMaster();
        $verified = app(TeacherIdentityVerifier::class)->verifyPair($this->pair());
        $this->assertSame($master->id, $verified->id);
        $this->assertArrayNotHasKey('teacher_dob', $verified->toArray());
    }

    public function test_missing_credential_is_not_a_null_equals_null_bypass(): void
    {
        $this->createMaster(['teacher_dob' => null]);
        $this->expectException(ValidationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair(['password' => null]));
    }

    public function test_empty_stored_credential_never_authenticates(): void
    {
        $this->createMaster(['teacher_dob' => '']);
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair());
    }

    public function test_missing_master_record_does_not_authenticate(): void
    {
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair());
    }

    public function test_teacher_name_and_code_must_identify_the_same_person(): void
    {
        $this->createMaster();
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair(['teacher_name' => 'Another Teacher']));
    }

    public function test_student_name_and_code_must_identify_the_same_assignment(): void
    {
        $this->createMaster();
        $this->createMaster(['student_name' => 'Another Student', 'student_code' => 'S-OTHER']);
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair(['student_code' => 'S-OTHER']));
    }

    public function test_teacher_cannot_submit_for_another_teachers_student(): void
    {
        $this->createMaster();
        $this->createMaster(['teacher_name' => 'Another Teacher', 'teacher_code' => 'T-OTHER', 'student_name' => 'Another Student', 'student_code' => 'S-OTHER']);
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair(['student_name' => 'Another Student', 'student_code' => 'S-OTHER']));
    }

    public function test_dob_alias_works_without_being_returned(): void
    {
        $this->createMaster();
        $teacher = app(TeacherIdentityVerifier::class)->verifyTeacher(['teacher_name' => 'QA Teacher', 'dob' => '01/01/1990']);
        $this->assertSame('T-QA', $teacher->teacher_code);
        $this->assertArrayNotHasKey('teacher_dob', $teacher->toArray());
    }

    public function test_explicit_empty_password_cannot_fall_back_to_dob_alias(): void
    {
        $this->createMaster();
        $this->expectException(ValidationException::class);
        app(TeacherIdentityVerifier::class)->verifyTeacher(['teacher_name' => 'QA Teacher', 'password' => '', 'dob' => '01/01/1990']);
    }

    public function test_ambiguous_teacher_names_are_rejected_without_a_code(): void
    {
        $this->createMaster();
        $this->createMaster(['teacher_code' => 'T-OTHER']);
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyTeacher(['teacher_name' => 'QA Teacher', 'dob' => '01/01/1990']);
    }

    public function test_teacher_code_can_disambiguate_for_code_based_reports(): void
    {
        $this->createMaster();
        $this->createMaster(['teacher_code' => 'T-OTHER']);
        $teacher = app(TeacherIdentityVerifier::class)->verifyTeacher(['teacher_name' => 'QA Teacher', 'teacher_code' => 'T-OTHER', 'dob' => '01/01/1990']);
        $this->assertSame('T-OTHER', $teacher->teacher_code);
    }
    public function test_inconsistent_teacher_credentials_across_assignments_are_rejected(): void
    {
        $this->createMaster();
        $this->createMaster(['student_name' => 'Another Student', 'student_code' => 'S-OTHER', 'teacher_dob' => '02/02/1991']);
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyTeacher(['teacher_name' => 'QA Teacher', 'dob' => '01/01/1990']);
    }

    public function test_conflicting_duplicate_assignment_rates_are_rejected(): void
    {
        $this->createMaster();
        $this->createMaster(['hour_payment' => '999.00']);
        $this->expectException(AuthorizationException::class);
        app(TeacherIdentityVerifier::class)->verifyPair($this->pair());
    }

    public function test_identical_duplicate_assignment_does_not_select_arbitrary_rates(): void
    {
        $this->createMaster(); $this->createMaster();
        $this->assertSame('100.00', app(TeacherIdentityVerifier::class)->verifyPair($this->pair())->hour_payment);
    }
}
