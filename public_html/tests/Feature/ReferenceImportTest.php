<?php
namespace Tests\Feature;

use App\Http\Controllers\admin\GoogleSpreadsheetController;
use App\Http\Controllers\admin\MasterController;
use App\Http\Controllers\admin\StudentsController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\TeacherReportController;
use App\Models\DueReport;
use App\Models\Master;
use App\Models\Students;
use App\Models\Teacher;
use App\Models\TeacherReport;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferenceImportTest extends TestCase
{
    private function masterRow(array $changes = []): array
    {
        return array_replace(['S-QA', 'QA Student', 'T-QA', 'QA Teacher', '01/01/1990', '100', '20', '80', 'QA'], $changes);
    }

    private function values(array $rows): array
    {
        return array_merge([['Header']], $rows);
    }

    private function rejected(callable $operation): void
    {
        try { $operation(); $this->fail('Malformed snapshot was accepted.'); }
        catch (ValidationException $exception) {
            $this->assertStringNotContainsString('01/01/1990', json_encode($exception->errors()));
        }
    }

    public function test_disabled_integrations_never_attempt_credentials_or_google_network(): void
    {
        $this->actingAs($this->createAdmin());
        config(['integrations.sheets.enabled' => false, 'integrations.sheets.credentials_path' => '/must/not/be/read.json']);
        foreach (['syncTeachers', 'syncStudents', 'syncMasters', 'syncTeacherReportData', 'syncDueReportData'] as $route) {
            $this->getJson(route($route))->assertStatus(405);
            $this->postJson(route($route))->assertStatus(503)->assertJsonPath('message', 'Spreadsheet synchronization is not enabled.');
        }
    }

    public function test_missing_configuration_fails_before_network(): void
    {
        config(['integrations.sheets.enabled' => true, 'integrations.sheets.spreadsheet_id' => '']);
        try { app(SheetSnapshotReader::class)->read('TEACHER'); $this->fail('Missing configuration accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(503, $exception->getStatusCode()); }
    }

    public function test_master_snapshot_deduplicates_identical_assignments_and_allows_negative_profit(): void
    {
        $this->createMaster(['student_code' => 'OLD']);
        $row = $this->masterRow([6 => '-20.50']);
        app(MasterController::class)->importSnapshot($this->values([$row, $row]));
        $this->assertDatabaseCount('masters', 1);
        $this->assertDatabaseHas('masters', ['student_code' => 'S-QA', 'profit_per_hour' => '-20.50', 'hour_payment' => '100.00']);
    }

    public function test_conflicting_master_assignments_credentials_names_and_malformed_rates_preserve_snapshot(): void
    {
        $old = $this->createMaster(['student_code' => 'OLD']);
        foreach ([
            [$this->masterRow(), $this->masterRow([5 => '200'])],
            [$this->masterRow(), $this->masterRow([0 => 'S2', 4 => '02/01/1990'])],
            [$this->masterRow(), $this->masterRow([2 => 'T2'])],
            [$this->masterRow(), $this->masterRow([2 => 'T2', 3 => 'Other', 1 => 'Other student'])],
            [$this->masterRow([5 => 'bad-rate'])], [$this->masterRow([7 => '-5'])],
            [$this->masterRow([4 => ''])], [['partial']], [],
        ] as $rows) {
            $this->rejected(fn () => app(MasterController::class)->importSnapshot($this->values($rows)));
            $this->assertDatabaseCount('masters', 1);
            $this->assertDatabaseHas('masters', ['id' => $old->id, 'student_code' => 'OLD']);
        }
    }

    public function test_teacher_reconciliation_preserves_retained_ids_and_never_relabels_removed_ids(): void
    {
        $retained = Teacher::create(['teacher_code' => 'T1', 'teacher_name' => 'Teacher One']);
        $removed = Teacher::create(['teacher_code' => 'T2', 'teacher_name' => 'Teacher Two']);
        app(GoogleSpreadsheetController::class)->importSnapshot($this->values([
            ['Teacher One', 'T1', 'active', 'staff', 'Maths', 'English'],
            ['Teacher Three', 'T3', 'active', 'staff', 'Science', 'English'],
        ]));
        $this->assertSame($retained->id, Teacher::where('teacher_code', 'T1')->first()->id);
        $this->assertNull(Teacher::find($removed->id));
        $this->assertGreaterThan($removed->id, Teacher::where('teacher_code', 'T3')->first()->id);
    }

    public function test_student_reconciliation_preserves_ids_and_rejects_duplicate_codes_and_names(): void
    {
        $student = Students::create(['student_code' => 'S1', 'student_name' => 'Student One']);
        $row = ['team', 'Student One', 'active', 'S1', '100', 'category', 'source', '4'];
        app(StudentsController::class)->importSnapshot($this->values([$row, $row]));
        $this->assertSame($student->id, Students::first()->id);
        $this->assertDatabaseCount('students', 1);
        foreach ([array_replace($row, [1 => 'Other Name']), array_replace($row, [3 => 'S2']), array_replace($row, [4 => '-1'])] as $bad) {
            $this->rejected(fn () => app(StudentsController::class)->importSnapshot($this->values([$row, $bad])));
            $this->assertDatabaseCount('students', 1);
        }
    }

    public function test_ambiguous_existing_reference_codes_abort_reconciliation_without_partial_updates(): void
    {
        Teacher::create(['teacher_code' => 'T1', 'teacher_name' => 'One']);
        Teacher::create(['teacher_code' => 'T1', 'teacher_name' => 'Two']);
        $this->rejected(fn () => app(GoogleSpreadsheetController::class)->importSnapshot($this->values([
            ['Updated', 'T1', 'active', 'staff', 'Maths', 'English'],
        ])));
        $this->assertDatabaseCount('teachers', 2);
        $this->assertDatabaseMissing('teachers', ['teacher_name' => 'Updated']);
    }

    public function test_teacher_report_import_validates_full_date_and_ignores_optional_dob_column(): void
    {
        $row = ['Teacher', 'T1', 'Student', 'S1', 'Maths', '20/10/2026', '2026-10-20 12:00:00', 'Chapter', 'Topic', '1.5', 'yes', 'legacy-private-credential'];
        app(TeacherReportController::class)->importSnapshot($this->values([$row]));
        $this->assertDatabaseHas('teacher_reports', ['date' => '2026-10-20', 'hour_taken' => '1.50', 'dob' => null]);
        $this->rejected(fn () => app(TeacherReportController::class)->importSnapshot($this->values([$row, array_replace($row, [5 => '31/02/2026'])])));
        $this->assertDatabaseCount('teacher_reports', 1);
        app(TeacherReportController::class)->importSnapshot($this->values([array_slice($row, 0, 11)]));
        $this->assertDatabaseCount('teacher_reports', 1);
    }

    public function test_due_snapshot_rejects_partial_identity_rows_instead_of_silently_dropping_them(): void
    {
        DueReport::create(['student_name' => 'Retained', 'student_code' => 'S0']);
        $row = array_fill(0, 20, ''); $row[0] = 'Student'; $row[1] = 'S1';
        $this->rejected(fn () => app(DueController::class)->importSnapshot($this->values([$row, ['', 'S2']])));
        $this->assertDatabaseHas('due_reports', ['student_name' => 'Retained']);
        app(DueController::class)->importSnapshot($this->values([[], $row, ['']]));
        $this->assertDatabaseCount('due_reports', 1);
        $this->assertDatabaseHas('due_reports', ['student_name' => 'Student']);
    }

    public function test_database_failure_rolls_back_snapshot_deletion(): void
    {
        $this->createMaster(['student_code' => 'OLD']);
        // Inject once inside the service transaction, without DDL that implicitly commits on MySQL.
        $armed = true;
        DB::listen(function ($query) use (&$armed) {
            $sql = str_replace(['`', '"'], '', strtolower($query->sql));
            if ($armed && strpos($sql, 'insert into masters ') === 0) {
                $armed = false;
                throw new QueryException($query->connectionName, $query->sql, $query->bindings, new \RuntimeException('Synthetic insert failure'));
            }
        });
        try { app(MasterController::class)->importSnapshot($this->values([$this->masterRow()])); $this->fail('Expected insertion failure.'); }
        catch (QueryException $exception) { $this->assertDatabaseHas('masters', ['student_code' => 'OLD']); }
        finally { $armed = false; }
    }

    public function test_google_omitted_trailing_optional_cells_are_preserved_as_empty_fields(): void
    {
        app(GoogleSpreadsheetController::class)->importSnapshot($this->values([['Teacher', 'T1']]));
        $this->assertDatabaseHas('teachers', ['teacher_code' => 'T1', 'medium' => '']);
        app(StudentsController::class)->importSnapshot($this->values([['team', 'Student', 'active', 'S1']]));
        $this->assertDatabaseHas('students', ['student_code' => 'S1', 'hours' => '', 'hour_rate' => '']);
        app(MasterController::class)->importSnapshot($this->values([array_slice($this->masterRow(), 0, 8)]));
        $this->assertDatabaseHas('masters', ['teacher_code' => 'T-QA', 'team' => '']);
        app(DueController::class)->importSnapshot($this->values([['Student', 'S1']]));
        $this->assertDatabaseHas('due_reports', ['student_code' => 'S1', 'current_status' => '']);
    }

    public function test_admin_tables_search_real_columns_without_credentials_or_subject_actions(): void
    {
        $this->actingAs($this->createAdmin()); $this->createMaster();
        Teacher::create(['teacher_name' => 'QA Teacher', 'teacher_code' => 'T-QA']);
        Students::create(['student_name' => 'QA Student', 'student_code' => 'S-QA']);
        TeacherReport::create(['teacher_name' => 'QA Teacher', 'student_name' => 'QA Student']);
        foreach (['master', 'teachers', 'students', 'workreport'] as $resource) {
            $this->getJson('/admin/'.$resource.'?search[value]=QA', ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertJsonCount(1, 'data')->assertDontSee('01/01/1990')->assertDontSee('"teacher_dob"')->assertDontSee('subjects/');
        }
    }
}

