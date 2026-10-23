<?php
namespace Tests\Feature;

use App\Models\Master;
use App\Models\Subject;
use App\Models\WorkHour;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceSubmissionTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        $master = Master::first() ?: $this->createMaster();
        $subject = Subject::first() ?: $this->createSubject();
        return array_merge([
            'submission_key' => (string) Str::uuid(), 'teacher_name' => $master->teacher_name,
            'teacher_code' => $master->teacher_code, 'student_name' => $master->student_name,
            'student_code' => $master->student_code, 'password' => '01/01/1990',
            'subject' => $subject->id, 'date' => '2026-10-20', 'chapter' => 'Fractions',
            'topic' => 'Addition', 'hour_taken' => '1.25', 'homework_status' => 'yes',
        ], $overrides);
    }

    public function test_missing_unknown_and_wrong_identity_never_create_attendance(): void
    {
        $payload = $this->payload();
        foreach ([['password' => ''], ['teacher_name' => 'Unknown', 'password' => ''], ['password' => 'wrong'], ['student_code' => 'different'], ['teacher_code' => 'different']] as $change) {
            $response = $this->postJson('/upload-workhour', array_merge($payload, $change));
            $this->assertContains($response->status(), [403, 422]);
        }
        $this->assertDatabaseCount('work_hours', 0);
    }

    public function test_amounts_come_from_assignment_and_credentials_are_not_stored(): void
    {
        $payload = $this->payload(['total_amount' => '999999', 'hour_payment' => '999999', 'profit_per_hour' => '999999', 'payment_per_hour' => '999999', 'dob' => 'fake']);
        $this->postJson('/upload-workhour', $payload)->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('work_hours', ['hour_payment' => '100.00', 'payment_per_hour' => '80.00', 'profit_per_hour' => '20.00', 'total_amount' => '125.00', 'dob' => null]);
        $this->assertStringNotContainsString('01/01/1990', json_encode(WorkHour::first()->getAttributes()));
    }

    public function test_replay_returns_original_and_conflict_or_wrong_credentials_cannot_replay(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/upload-workhour', $payload)->assertOk();
        $this->postJson('/upload-workhour', $payload)->assertOk()->assertJson(['id' => $first->json('id')]);
        $this->postJson('/upload-workhour', array_merge($payload, ['hour_taken' => '2']))->assertStatus(409);
        $this->postJson('/upload-workhour', array_merge($payload, ['password' => 'wrong']))->assertStatus(403);
        $this->assertDatabaseCount('work_hours', 1);
    }

    public function test_replay_survives_rate_and_subject_changes_without_repricing(): void
    {
        $payload = $this->payload();
        $this->postJson('/upload-workhour', $payload)->assertOk();
        Master::query()->update(['hour_payment' => '500.00']);
        Subject::query()->delete();
        $this->postJson('/upload-workhour', $payload)->assertOk();
        $this->assertDatabaseHas('work_hours', ['hour_payment' => '100.00', 'total_amount' => '125.00']);
    }

    public function test_invalid_hours_dates_subject_or_key_do_not_write(): void
    {
        $payload = $this->payload();
        foreach ([['hour_taken' => '-1'], ['hour_taken' => '0'], ['hour_taken' => '25'], ['hour_taken' => '1.234'], ['date' => 'not-a-date'], ['subject' => 999], ['submission_key' => 'invalid']] as $change) {
            $this->postJson('/upload-workhour', array_merge($payload, $change))->assertStatus(422);
        }
        $this->assertDatabaseCount('work_hours', 0);
    }

    public function test_bad_assigned_rates_fail_but_negative_profit_is_not_a_prohibited_loss(): void
    {
        $payload = $this->payload();
        Master::query()->update(['hour_payment' => 'bad']);
        $this->postJson('/upload-workhour', $payload)->assertStatus(422);
        Master::query()->update(['hour_payment' => '100.00', 'profit_per_hour' => '-20.00']);
        $this->postJson('/upload-workhour', $payload)->assertOk();
        $this->assertDatabaseHas('work_hours', ['profit_per_hour' => '-20.00', 'total_amount' => '125.00']);
    }

    public function test_admin_correction_ignores_client_money_and_preserves_historical_rate_and_identity(): void
    {
        $payload = $this->payload();
        $this->postJson('/upload-workhour', $payload)->assertOk();
        $record = WorkHour::first(); $originalSubmitted = $record->date_submitted;
        Master::query()->update(['hour_payment' => '500.00']);
        $this->actingAs($this->createAdmin());
        $edit = array_merge($payload, ['subject' => 'QA Mathematics', 'student_names' => 'QA Student', 'hour_taken' => '2', 'hour_payment' => '999', 'total_amount' => '999', 'date_submitted' => '1900-01-01']);
        $this->putJson('/admin/workhour/'.$record->id, $edit)->assertOk();
        $record->refresh();
        $this->assertSame('200.00', $record->total_amount);
        $this->assertSame('100.00', $record->hour_payment);
        $this->assertSame($originalSubmitted, $record->date_submitted);
        $this->putJson('/admin/workhour/'.$record->id, array_merge($edit, ['student_names' => 'Someone else']))->assertStatus(422);
    }

    public function test_attendance_report_and_lookup_scope_before_rows_and_counts_and_hide_dob(): void
    {
        $payload = $this->payload(); $this->postJson('/upload-workhour', $payload)->assertOk();
        WorkHour::create(['teacher_name' => 'Another', 'teacher_code' => 'T-PRIVATE', 'student_name' => 'Private student', 'dob' => 'private-credential']);
        $this->actingAs($this->createMember());
        foreach (['/attendanceReport', '/student-attendance-report'] as $path) {
            $this->postJson($path)->assertStatus(422);
            $this->postJson($path, ['teacher_name' => 'QA Teacher', 'dob' => '01/01/1990'])->assertOk()->assertJsonPath('recordsTotal', 1)->assertDontSee('Private student')->assertDontSee('01/01/1990')->assertDontSee('"dob"');
            $this->getJson($path, ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(405);
        }
        $this->postJson('/get-students', ['teacher_name' => 'QA Teacher', 'dob' => '01/01/1990'])->assertOk()->assertJsonCount(1, 'students')->assertDontSee('Private student');
    }

    public function test_public_assignment_lookup_is_verified_and_omits_rates_and_credentials(): void
    {
        $this->createMaster();
        $this->postJson('/studentinfom', ['teacher_name' => 'QA Teacher'])->assertStatus(422);
        $this->postJson('/studentinfom', ['teacher_name' => 'QA Teacher', 'password' => '01/01/1990'])->assertOk()->assertJsonCount(1, 'students')->assertDontSee('hour_payment')->assertDontSee('01/01/1990');
    }

    public function test_credential_aliases_never_flash_into_old_input(): void
    {
        $response = $this->from('/timetable')->post('/get-teacher-dob', ['dob' => 'secret', 'teacher_dob' => 'secret2', 'confirm_password' => 'secret3']);
        $response->assertRedirect('/timetable');
        $this->assertArrayNotHasKey('dob', session()->getOldInput());
        $this->assertArrayNotHasKey('teacher_dob', session()->getOldInput());
        $this->assertArrayNotHasKey('confirm_password', session()->getOldInput());
    }

    public function test_credential_throttle_is_shared_across_routes_and_not_other_teachers(): void
    {
        for ($i = 0; $i < 30; $i++) $this->postJson('/get-teacher-dob', ['teacher_name' => 'Limit Teacher', 'dob' => 'wrong'])->assertStatus(403);
        $this->postJson('/studentinfom', ['teacher_name' => 'LIMIT TEACHER', 'password' => 'wrong'])->assertStatus(429);
        $this->postJson('/get-teacher-dob', ['teacher_name' => 'Other Teacher', 'dob' => 'wrong'])->assertStatus(403);
    }
    public function test_admin_edit_view_renders_canonical_hours_and_readonly_snapshots(): void
    {
        $payload = $this->payload(['hour_taken' => '1']);
        $this->postJson('/upload-workhour', $payload)->assertOk();
        $this->actingAs($this->createAdmin());
        $row = WorkHour::first();
        $this->get('/admin/workhour/'.$row->id.'/edit', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('value="1" selected', false)
            ->assertSee('value="100.00" readonly', false)->assertSee('QA Student');
    }

}
