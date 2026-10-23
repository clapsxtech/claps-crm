<?php
namespace Tests\Feature;

use App\Models\WorkHour;
use App\Modules\Attendance\Services\ImportAttendanceSnapshot;
use App\Modules\Integrations\Services\SheetSnapshotReader;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegrationSafetyTest extends TestCase
{
    private function rows(): array
    {
        return [['header'], ['QA Teacher', 'T-QA', 'QA Student', 'S-QA', 'Math', '20/10/2026', '2026-10-21 12:00:00', 'Fractions', 'Addition', '1', 'yes', 'legacy-secret', '100', '-20', '80', '100']];
    }

    public function test_attendance_import_rejects_local_submissions_before_network(): void
    {
        $this->actingAs($this->createAdmin());
        WorkHour::create(['teacher_name' => 'Local', 'submission_key' => (string) Str::uuid()]);
        $reader = \Mockery::mock(SheetSnapshotReader::class); $reader->shouldNotReceive('read');
        $this->app->instance(SheetSnapshotReader::class, $reader);
        $this->postJson('/admin/syncWorkHoureReportData')->assertStatus(409)->assertSee('Reconcile');
        $this->assertDatabaseCount('work_hours', 1);
    }

    public function test_legacy_attendance_snapshot_validates_and_discards_credentials(): void
    {
        WorkHour::create(['teacher_name' => 'Old snapshot']);
        app(ImportAttendanceSnapshot::class)->replace($this->rows());
        $this->assertDatabaseCount('work_hours', 1);
        $this->assertDatabaseHas('work_hours', ['teacher_name' => 'QA Teacher', 'profit_per_hour' => '-20.00', 'dob' => null]);
        $this->assertDatabaseMissing('work_hours', ['teacher_name' => 'Old snapshot']);
    }

    public function test_empty_and_malformed_attendance_snapshots_preserve_original(): void
    {
        WorkHour::create(['teacher_name' => 'Original']);
        foreach ([[], [['header']], [['header'], ['short']], [['header'], array_replace($this->rows()[1], [5 => '31/02/2026'])]] as $rows) {
            try { app(ImportAttendanceSnapshot::class)->replace($rows); $this->fail('Expected validation failure'); }
            catch (ValidationException $exception) { $this->assertDatabaseHas('work_hours', ['teacher_name' => 'Original']); }
        }
    }

    public function test_sync_get_is_rejected_and_post_requires_csrf_outside_testing_bypass(): void
    {
        $this->actingAs($this->createAdmin());
        $this->getJson('/admin/syncWorkHoureReportData')->assertStatus(405);
        $this->app->instance('env', 'local');
        try { $this->postJson('/admin/syncWorkHoureReportData')->assertStatus(419); }
        finally { $this->app->instance('env', 'testing'); }
    }

    public function test_successful_login_redirects_to_existing_dashboard(): void
    {
        $admin = $this->createAdmin();
        $response = $this->post('/login', ['email' => $admin->email, 'password' => 'Local-Testing-Only-2026']);
        $response->assertRedirect('/');
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_unsupported_delete_dialogs_are_not_advertised_or_enabled(): void
    {
        $this->actingAs($this->createAdmin()); $this->createSubject();
        $this->getJson('/admin/subjects', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertDontSee('fa-trash');
        $this->getJson('/admin/delete/subjects/1', ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(404);
        $this->getJson('/admin/delete/marklist/1', ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(404);
    }

    public function test_supported_attendance_delete_dialog_and_action_use_consistent_contract(): void
    {
        $this->actingAs($this->createAdmin());
        $row = WorkHour::create(['teacher_name' => 'QA Teacher']);
        $this->getJson('/admin/delete/workhour/'.$row->id, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('/admin/workhour/'.$row->id);
        $this->deleteJson('/admin/workhour/'.$row->id, [], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('work_hours', ['id' => $row->id]);
    }
}
