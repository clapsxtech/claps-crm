<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\Attendance;
use App\Modules\Erp\Services\Finance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ErpFinanceAttendanceTest extends TestCase
{
    // This fixture deliberately builds a separate SQLite schema in setUp(). Do not
    // begin a RefreshDatabase transaction on a PDO that setUp() then replaces.
    // Each test gets a newly purged in-memory connection and explicit migrations.
    public function refreshDatabase(): void
    {
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'erp.finance_policy' => 'admin_import_delivered_only', 'erp.payroll_policy' => 'unconfigured']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) { $table->increments('id'); });
        DB::table('users')->insert(['id' => 1]);
        foreach (glob(database_path('migrations/*erp*.php')) as $path) {
            $migration = require_once $path;
            if (is_object($migration)) $migration->up();
            else {
                $name = \Illuminate\Support\Str::studly(implode('_', array_slice(explode('_', basename($path, '.php')), 4)));
                (new $name())->up();
            }
        }
        DB::table('erp_units')->insert([['id' => 1, 'code' => 'TEST-A', 'name' => 'Synthetic A'], ['id' => 2, 'code' => 'TEST-B', 'name' => 'Synthetic B']]);
        DB::table('erp_students')->insert(['id' => 1, 'unit_id' => 1, 'code' => 'SYN-STUDENT', 'name' => 'Synthetic Student', 'registered_on' => '2026-01-01', 'timezone' => 'Asia/Kolkata', 'status' => 'active', 'grade' => '8', 'syllabus' => 'Test']);
        DB::table('erp_teachers')->insert(['id' => 1, 'code' => 'SYN-TEACHER', 'name' => 'Synthetic Teacher', 'timezone' => 'Asia/Kolkata', 'status' => 'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id' => 1, 'unit_id' => 1]);
        DB::table('erp_subjects')->insert(['id' => 1, 'name' => 'Synthetic Subject']);
        DB::table('erp_enrollments')->insert(['id' => 1, 'unit_id' => 1, 'student_id' => 1, 'teacher_id' => 1, 'subject_id' => 1, 'starts_on' => '2026-01-01', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR', 'status' => 'active']);
        $this->lesson(1, '2026-10-20 10:00:00');
    }

    private function lesson(int $id, string $start, string $status = 'scheduled'): void
    {
        DB::table('erp_lessons')->insert(['id' => $id, 'unit_id' => 1, 'enrollment_id' => 1, 'student_id' => 1, 'teacher_id' => 1, 'subject_id' => 1, 'starts_at' => $start, 'ends_at' => \Carbon\Carbon::parse($start)->addHour()->format('Y-m-d H:i:s'), 'status' => $status, 'kind' => 'regular', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR']);
    }

    private function invalid(callable $operation): void
    {
        try { $operation(); $this->fail('Expected a domain validation failure.'); }
        catch (ValidationException $expected) { $this->assertNotEmpty($expected->errors()); }
    }

    public function test_payment_exact_replay_and_conflicting_reference(): void
    {
        $finance = new Finance();
        $payload = ['student_id' => 1, 'amount_paise' => 50000, 'reference' => 'SYN-RECEIPT-001'];
        $first = $finance->payment(1, 1, $payload);
        $this->assertEquals($first->id, $finance->payment(1, 1, $payload)->id);
        $this->invalid(function () use ($finance, $payload) { $finance->payment(1, 1, array_merge($payload, ['amount_paise' => 50001])); });
        $this->assertSame(1, DB::table('erp_ledger')->count());
        $this->assertSame(50000, $finance->balance(1, 1));
    }

    public function test_submission_and_approval_are_idempotent_and_rates_are_frozen(): void
    {
        $service = new Attendance();
        $record = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 45]);
        $this->assertEquals($record->id, $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 45])->id);
        $this->assertSame(0, DB::table('erp_ledger')->count());
        DB::table('erp_enrollments')->where('id', 1)->update(['student_rate_paise' => 99999]);
        $this->assertFalse($service->approve(1, 1, $record->id)->financial_posted);
        $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'BATCH-ONE']);
        $service->approve(1, 1, $record->id);
        $this->assertSame(1, DB::table('erp_ledger')->count());
        $this->assertSame(-9000, (new Finance())->balance(1, 1));
        $this->assertSame(0, (int) DB::table('erp_ledger')->where('kind', 'teacher_accrual')->count());
    }

    public function test_unconfigured_policy_never_charges_and_can_be_explicitly_enabled(): void
    {
        config(['erp.finance_policy' => 'unconfigured']);
        $service = new Attendance();
        $record = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $this->assertFalse($service->approve(1, 1, $record->id)->financial_posted);
        $this->assertSame(0, DB::table('erp_ledger')->count());
        config(['erp.finance_policy' => 'admin_import_delivered_only']);
        $this->assertFalse($service->approve(1, 1, $record->id)->financial_posted);
        $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'BATCH-ONE']);
    }

    public function test_same_day_legitimate_lesson_requires_reply_but_is_not_discarded(): void
    {
        $service = new Attendance();
        $first = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $this->lesson(2, '2026-10-20 14:00:00');
        $second = $service->submit(1, 1, ['lesson_id' => 2, 'actual_minutes' => 30]);
        $this->assertSame(2, DB::table('erp_attendance')->count());
        $review = DB::table('erp_duplicate_reviews')->first();
        $this->invalid(function () use ($service, $second) { $service->approve(1, 1, $second->id); });
        $this->invalid(function () use ($service, $review) { $service->resolveDuplicate(1, 1, $review->id, ['decision' => 'genuine', 'reason' => 'Extra lesson']); });
        $service->resolveDuplicate(1, 1, $review->id, ['decision' => 'genuine', 'teacher_reply' => 'Both sessions were delivered.', 'reason' => 'Confirmed extra lesson']);
        $service->approve(1, 1, $first->id);
        $service->approve(1, 1, $second->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$first->id, $second->id], 'reference' => 'BATCH-TWO']);
        $this->assertSame(-18000, (new Finance())->balance(1, 1));
    }

    public function test_correction_compensates_then_reapproval_posts_new_generation(): void
    {
        $service = new Attendance();
        $record = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $service->approve(1, 1, $record->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'BATCH-BEFORE']);
        $service->correct(1, 1, $record->id, ['actual_minutes' => 30, 'reason' => 'Teacher corrected duration.']);
        $this->assertSame(0, (new Finance())->balance(1, 1));
        $this->assertSame(1, DB::table('erp_ledger')->where('kind', 'reversal')->count());
        $service->correct(1, 1, $record->id, ['actual_minutes' => 30, 'reason' => 'Teacher corrected duration.']);
        $service->approve(1, 1, $record->id);
        $service->approve(1, 1, $record->id);
        $this->assertSame(0, (new Finance())->balance(1, 1));
        $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'BATCH-AFTER']);
        $this->assertSame(-6000, (new Finance())->balance(1, 1));
        $this->assertSame(3, DB::table('erp_ledger')->count());
        $this->assertSame(1, DB::table('erp_audit')->where('action', 'attendance.corrected')->count());
    }

    public function test_cross_unit_and_undelivered_lessons_are_rejected(): void
    {
        $service = new Attendance();
        $this->invalid(function () use ($service) { $service->submit(2, 1, ['lesson_id' => 1, 'actual_minutes' => 60]); });
        $this->invalid(function () { (new Finance())->payment(2, 1, ['student_id' => 1, 'amount_paise' => 100, 'reference' => 'SYN-X']); });
        foreach (['cancelled', 'no_show', 'rescheduled'] as $status) {
            DB::table('erp_lessons')->where('id', 1)->update(['status' => $status]);
            $this->invalid(function () use ($service) { $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]); });
        }
        $this->assertSame(0, DB::table('erp_ledger')->count());
    }

    public function test_batch_posting_failure_rolls_back_batch_and_all_entries(): void
    {
        $service = new Attendance();
        $record = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $service->approve(1, 1, $record->id);
        DB::unprepared("CREATE TRIGGER synthetic_fail_charge BEFORE INSERT ON erp_ledger WHEN NEW.kind = 'lesson_charge' BEGIN SELECT RAISE(ABORT, 'synthetic posting failure'); END");
        try {
            $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'BATCH-FAIL']);
            $this->fail('Expected synthetic posting failure.');
        } catch (\Illuminate\Database\QueryException $expected) {
            $this->assertSame(0, DB::table('erp_ledger')->count());
            $this->assertSame('approved', DB::table('erp_attendance')->where('id', $record->id)->value('status'));
            $this->assertSame(0, DB::table('erp_attendance_batches')->count());
        }
    }

    public function test_admin_imports_submitted_duplicate_candidates_then_reverses_rejected_charge(): void
    {
        $service = new Attendance();
        $first = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $this->lesson(2, '2026-10-20 14:00:00');
        $second = $service->submit(1, 1, ['lesson_id' => 2, 'actual_minutes' => 60]);
        $service->importBatch(1, 1, ['attendance_ids' => [$first->id, $second->id], 'reference' => 'IMPORT-PENDING']);
        $this->assertSame(-24000, (new Finance())->balance(1, 1));
        $this->assertSame(1, DB::table('erp_duplicate_reviews')->where('status', 'pending')->count());
        $review = DB::table('erp_duplicate_reviews')->first();
        $service->resolveDuplicate(1, 1, $review->id, ['decision' => 'duplicate', 'duplicate_attendance_id' => $second->id, 'teacher_reply' => 'Second entry was wrong.', 'reason' => 'Correct imported duplicate']);
        $this->assertSame(-12000, (new Finance())->balance(1, 1));
        $this->assertSame(1, DB::table('erp_ledger')->where('kind', 'reversal')->count());
        $this->assertSame(0, DB::table('erp_ledger')->where('kind', 'teacher_accrual')->count());
    }

    public function test_submission_rechecks_lesson_state_after_parent_serialization(): void
    {
        // Deterministic interleaving regression, not a substitute for a two-connection MySQL race test.
        $changed = false;
        DB::listen(function ($query) use (&$changed) {
            if (!$changed && strpos($query->sql, 'select') === 0 && strpos($query->sql, 'erp_teachers') !== false) {
                $changed = true;
                DB::table('erp_lessons')->where('id', 1)->update(['status' => 'cancelled']);
            }
        });
        $this->invalid(function () { (new Attendance())->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]); });
        $this->assertTrue($changed);
        $this->assertSame(0, DB::table('erp_attendance')->count());
        $this->assertSame(0, DB::table('erp_ledger')->count());
    }

    public function test_future_lesson_cannot_receive_delivered_attendance(): void
    {
        $this->lesson(2, now('UTC')->addDay()->format('Y-m-d H:i:s'));
        $this->invalid(function () { (new Attendance())->submit(1, 1, ['lesson_id' => 2, 'actual_minutes' => 60]); });
        $this->assertSame(0, DB::table('erp_attendance')->count());
        $this->assertSame(0, DB::table('erp_ledger')->count());
    }

    public function test_batch_replay_snapshot_and_direct_posting_gate(): void
    {
        $service = new Attendance();
        $record = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $service->approve(1, 1, $record->id);
        $this->invalid(function () use ($record) { (new Finance())->postAttendance(1, 1, $record->id); });
        $batch = $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'IMMUTABLE']);
        $this->assertEquals($batch->id, $service->importBatch(1, 1, ['attendance_ids' => [$record->id], 'reference' => 'IMMUTABLE'])->id);
        $this->assertSame(1, DB::table('erp_ledger')->count());
        $this->invalid(function () use ($service) { $service->importBatch(1, 1, ['attendance_ids' => [999], 'reference' => 'IMMUTABLE']); });
        $service->correct(1, 1, $record->id, ['actual_minutes' => 30, 'reason' => 'Correction']);
        $service->approve(1, 1, $record->id);
        $this->invalid(function () use ($record, $batch) { (new Finance())->postAttendance(1, 1, $record->id, $batch->id); });
        $this->assertSame(60, json_decode($batch->snapshot, true)[0]['actual_minutes']);
        $this->assertSame(0, (new Finance())->balance(1, 1));
    }

    public function test_rejecting_older_candidate_resolves_related_edges_not_genuine_pair(): void
    {
        $service = new Attendance();
        $first = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $this->lesson(2, '2026-10-20 12:00:00');
        $second = $service->submit(1, 1, ['lesson_id' => 2, 'actual_minutes' => 60]);
        $this->lesson(3, '2026-10-20 14:00:00');
        $third = $service->submit(1, 1, ['lesson_id' => 3, 'actual_minutes' => 60]);
        $review = DB::table('erp_duplicate_reviews')->where('attendance_id', $second->id)->first();
        $service->resolveDuplicate(1, 1, $review->id, ['decision' => 'duplicate', 'duplicate_attendance_id' => $first->id, 'teacher_reply' => 'First entry was wrong.', 'reason' => 'Reject older entry']);
        $this->assertSame('rejected', DB::table('erp_attendance')->where('id', $first->id)->value('status'));
        $this->assertSame(1, DB::table('erp_duplicate_reviews')->where('status', 'pending')->count());
        $remaining = DB::table('erp_duplicate_reviews')->where('status', 'pending')->first();
        $service->resolveDuplicate(1, 1, $remaining->id, ['decision' => 'genuine', 'teacher_reply' => 'Both other lessons happened.', 'reason' => 'Confirmed']);
        $service->approve(1, 1, $second->id);
        $service->approve(1, 1, $third->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$second->id, $third->id], 'reference' => 'GENUINE-GROUP']);
        $this->assertSame(-24000, (new Finance())->balance(1, 1));
        $this->assertSame(0, DB::table('erp_ledger')->where('kind', 'teacher_accrual')->count());
    }

    public function test_duplicate_resolution_rejects_only_candidate(): void
    {
        $service = new Attendance();
        $first = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $this->lesson(2, '2026-10-20 14:00:00');
        $second = $service->submit(1, 1, ['lesson_id' => 2, 'actual_minutes' => 60]);
        $review = DB::table('erp_duplicate_reviews')->first();
        $service->resolveDuplicate(1, 1, $review->id, ['decision' => 'duplicate', 'duplicate_attendance_id' => $second->id, 'teacher_reply' => 'Second entry was a mistake.', 'reason' => 'Duplicate reporting']);
        $this->invalid(function () use ($service, $second) { $service->approve(1, 1, $second->id); });
        $service->approve(1, 1, $first->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$first->id], 'reference' => 'BATCH-GENUINE']);
        $this->assertSame(-12000, (new Finance())->balance(1, 1));
    }
    public function test_teacher_approval_before_import_is_independent_and_idempotent(): void
    {
        config(['erp.payroll_policy' => 'approved_attended_only']);
        $service = new Attendance();
        $row = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 45]);
        DB::table('erp_enrollments')->where('id', 1)->update(['teacher_rate_paise' => 99999]);
        $result = $service->approve(1, 1, $row->id);
        $this->assertTrue($result->teacher_financial_posted);
        $this->assertFalse($result->student_financial_posted);
        $this->assertSame(4500, (int) DB::table('erp_ledger')->where('teacher_id', 1)->sum('amount_paise'));
        $service->approve(1, 1, $row->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$row->id], 'reference' => 'PAY-APPROVE-FIRST']);
        $result = $service->approve(1, 1, $row->id);
        $this->assertTrue($result->teacher_financial_posted);
        $this->assertTrue($result->student_financial_posted);
        $this->assertSame(2, DB::table('erp_ledger')->count());
        $this->assertSame(-9000, (new Finance())->balance(1, 1));
    }

    public function test_import_before_approval_never_accrues_teacher_early_and_rounds_frozen_rate(): void
    {
        config(['erp.payroll_policy' => 'approved_attended_only']);
        DB::table('erp_lessons')->where('id', 1)->update(['teacher_rate_paise' => 101, 'student_rate_paise' => 103]);
        $service = new Attendance();
        $row = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 30]);
        $this->invalid(function () use ($row) { (new Finance())->postTeacherAttendance(1, 1, $row->id); });
        $service->importBatch(1, 1, ['attendance_ids' => [$row->id], 'reference' => 'PAY-IMPORT-FIRST']);
        $this->assertSame(0, DB::table('erp_ledger')->where('kind', 'teacher_accrual')->count());
        $result = $service->approve(1, 1, $row->id);
        $this->assertTrue($result->student_financial_posted);
        $this->assertTrue($result->teacher_financial_posted);
        $this->assertSame(51, (int) DB::table('erp_ledger')->where('teacher_id', 1)->sum('amount_paise'));
        $this->assertSame(-52, (new Finance())->balance(1, 1));
        $this->invalid(function () use ($row) { (new Finance())->postTeacherAttendance(2, 1, $row->id); });
    }

    public function test_demo_records_import_and_approval_but_has_no_student_or_teacher_entries(): void
    {
        config(['erp.payroll_policy' => 'approved_attended_only']);
        DB::table('erp_lessons')->where('id', 1)->update(['kind' => 'demo']);
        $service = new Attendance();
        $row = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $service->importBatch(1, 1, ['attendance_ids' => [$row->id], 'reference' => 'FREE-DEMO']);
        $result = $service->approve(1, 1, $row->id);
        $this->assertFalse($result->student_financial_posted);
        $this->assertFalse($result->teacher_financial_posted);
        $this->assertSame(0, DB::table('erp_ledger')->count());
        $this->assertSame(1, DB::table('erp_attendance_batches')->count());
        $this->assertSame('approved', $result->status);
    }

    public function test_correction_reverses_both_streams_then_requires_independent_actions(): void
    {
        config(['erp.payroll_policy' => 'approved_attended_only']);
        $service = new Attendance();
        $row = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $service->approve(1, 1, $row->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$row->id], 'reference' => 'BOTH-ORIGINAL']);
        $service->correct(1, 1, $row->id, ['actual_minutes' => 30, 'reason' => 'Correct both amounts']);
        $this->assertSame(2, DB::table('erp_ledger')->where('kind', 'reversal')->count());
        $this->assertSame(0, (int) DB::table('erp_ledger')->where('teacher_id', 1)->sum('amount_paise'));
        $this->assertSame(0, (new Finance())->balance(1, 1));
        $this->invalid(function () use ($row) { (new Finance())->postTeacherAttendance(1, 1, $row->id); });
        $service->importBatch(1, 1, ['attendance_ids' => [$row->id], 'reference' => 'BOTH-CORRECTED']);
        $this->assertSame(0, (int) DB::table('erp_ledger')->where('teacher_id', 1)->sum('amount_paise'));
        $this->assertSame(-6000, (new Finance())->balance(1, 1));
        $service->approve(1, 1, $row->id);
        $service->approve(1, 1, $row->id);
        $this->assertSame(3000, (int) DB::table('erp_ledger')->where('teacher_id', 1)->sum('amount_paise'));
        $this->assertSame(6, DB::table('erp_ledger')->count());
    }

    public function test_rejecting_previously_approved_duplicate_reverses_teacher_and_student(): void
    {
        config(['erp.payroll_policy' => 'approved_attended_only']);
        $service = new Attendance();
        $first = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        $service->approve(1, 1, $first->id);
        $service->importBatch(1, 1, ['attendance_ids' => [$first->id], 'reference' => 'DUP-PAID']);
        $this->lesson(2, '2026-10-20 14:00:00');
        $service->submit(1, 1, ['lesson_id' => 2, 'actual_minutes' => 60]);
        $review = DB::table('erp_duplicate_reviews')->first();
        $service->resolveDuplicate(1, 1, $review->id, ['decision' => 'duplicate', 'duplicate_attendance_id' => $first->id, 'teacher_reply' => 'First report was duplicated.', 'reason' => 'Reject earlier imported record']);
        $this->assertSame(0, (int) DB::table('erp_ledger')->where('teacher_id', 1)->sum('amount_paise'));
        $this->assertSame(0, (new Finance())->balance(1, 1));
        $this->assertSame(2, DB::table('erp_ledger')->where('kind', 'reversal')->count());
        $this->invalid(function () use ($first, $service) { $service->approve(1, 1, $first->id); });
    }

    public function test_teacher_accrual_failure_rolls_back_approval_and_cancelled_lesson_never_accrues(): void
    {
        config(['erp.payroll_policy' => 'approved_attended_only']);
        $service = new Attendance();
        $row = $service->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
        DB::unprepared("CREATE TRIGGER synthetic_fail_pay BEFORE INSERT ON erp_ledger WHEN NEW.kind = 'teacher_accrual' BEGIN SELECT RAISE(ABORT, 'synthetic payroll failure'); END");
        try { $service->approve(1, 1, $row->id); $this->fail('Expected synthetic failure.'); }
        catch (\Illuminate\Database\QueryException $expected) {
            $this->assertSame('submitted', DB::table('erp_attendance')->where('id', $row->id)->value('status'));
            $this->assertSame(0, DB::table('erp_ledger')->count());
        }
        DB::unprepared('DROP TRIGGER synthetic_fail_pay');
        DB::table('erp_lessons')->where('id', 1)->update(['status' => 'cancelled']);
        $this->invalid(function () use ($service, $row) { $service->approve(1, 1, $row->id); });
        $this->assertSame(0, DB::table('erp_ledger')->count());
    }

}
