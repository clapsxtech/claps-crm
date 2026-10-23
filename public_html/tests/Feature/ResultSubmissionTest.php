<?php

namespace Tests\Feature;

use App\Models\Marklist;
use App\Models\Performance;
use App\Models\Students;
use App\Models\Teacher;
use App\Models\Topic;
use App\Modules\Academics\Services\ResultSheetSerializer;
use App\Modules\Integrations\Contracts\SheetsTransport;
use App\Modules\Integrations\Models\IntegrationOutbox;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ResultSubmissionTest extends TestCase
{
    private function resultPayload(array $overrides = []): array
    {
        $master = $this->createMaster();
        $subject = $this->createSubject();

        return array_merge([
            'submission_key' => (string) Str::uuid(),
            'teacher_name' => $master->teacher_name, 'teacher_code' => $master->teacher_code,
            'student_name' => $master->student_name, 'student_code' => $master->student_code,
            'password' => '01/01/1990', 'subject' => $subject->id, 'chapter' => 'Fractions',
            'total_mark' => '20', 'mark_scored' => '18', 'mode_of_exam' => 'Written (live)',
            'examination_date' => '2026-10-20', 'remark' => 'Good progress',
        ], $overrides);
    }

    public function test_result_saves_once_and_replays_without_dispatching_google(): void
    {
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldNotReceive('append');
        $this->app->instance(SheetsTransport::class, $transport);
        $payload = $this->resultPayload();
        $first = $this->postJson('/upload-result', $payload)->assertOk()->assertJson(['success' => true, 'replayed' => false]);
        $this->postJson('/upload-result', $payload)->assertOk()->assertJson(['success' => true, 'id' => $first->json('id'), 'replayed' => true]);
        $this->assertDatabaseCount('marklists', 1);
        $this->assertDatabaseCount('integration_outbox', 1);
        $this->assertDatabaseHas('marklists', ['subject' => $payload['subject'], 'mark_scored' => '18']);
        $event = IntegrationOutbox::firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->assertCount(12, $event->payload['values'][0]);
        $this->assertSame(['QA Teacher', 'T-QA', 'QA Student', 'S-QA', 'QA Mathematics', 'Fractions', '18', '20', 'Written (live)', '20/10/2026', 'Good progress'], array_slice($event->payload['values'][0], 0, 11));
        $stored = json_encode($event->getAttributes());
        $this->assertStringNotContainsString('01/01/1990', $stored);
        $this->assertStringNotContainsString('password', $stored);
        $this->assertStringNotContainsString('teacher_dob', $stored);
    }

    public function test_same_key_changed_data_is_conflict_and_authentication_precedes_replay(): void
    {
        $payload = $this->resultPayload();
        $this->postJson('/upload-result', $payload)->assertOk();
        $this->postJson('/upload-result', array_merge($payload, ['mark_scored' => 17]))->assertStatus(409);
        $this->postJson('/upload-result', array_merge($payload, ['password' => 'wrong']))->assertStatus(403);
        $this->postJson('/upload-result', array_merge($payload, ['student_code' => 'someone-else']))->assertStatus(403);
        $this->assertDatabaseCount('marklists', 1);
        $this->assertDatabaseCount('integration_outbox', 1);
    }

    public function test_missing_key_and_invalid_academics_do_not_write(): void
    {
        $payload = $this->resultPayload();
        $missing = $payload;
        unset($missing['submission_key']);
        $this->postJson('/upload-result', $missing)->assertStatus(422)->assertJsonValidationErrors('submission_key');
        $this->postJson('/upload-result', array_merge($payload, ['subject' => 999999]))->assertStatus(422);
        $this->postJson('/upload-result', array_merge($payload, ['mark_scored' => 21]))->assertStatus(422);
        $this->postJson('/upload-result', array_merge($payload, ['total_mark' => 0]))->assertStatus(422);
        $this->postJson('/upload-result', array_merge($payload, ['examination_date' => '20/10/2026']))->assertStatus(422);
        $this->assertDatabaseCount('marklists', 0);
        $this->assertDatabaseCount('integration_outbox', 0);
    }

    public function test_failure_building_outbox_payload_rolls_back_the_academic_record_and_key(): void
    {
        $payload = $this->resultPayload();
        $serializer = Mockery::mock(ResultSheetSerializer::class);
        $serializer->shouldReceive('marklist')->once()->andThrow(new RuntimeException('Synthetic serializer failure'));
        $this->app->instance(ResultSheetSerializer::class, $serializer);
        $this->postJson('/upload-result', $payload)->assertStatus(500);
        $this->assertDatabaseCount('marklists', 0);
        $this->assertDatabaseCount('integration_outbox', 0);
        $this->app->instance(ResultSheetSerializer::class, new ResultSheetSerializer());
        $this->postJson('/upload-result', $payload)->assertOk();
        $this->assertDatabaseCount('marklists', 1);
    }

    public function test_performance_uses_same_authenticated_idempotent_outbox_boundary(): void
    {
        $payload = $this->resultPayload();
        $topic = Topic::create(['name' => 'Engagement']);
        $payload['topic'] = $topic->id;
        $first = $this->postJson('/performnce-upload', $payload)->assertOk();
        $this->postJson('/performnce-upload', $payload)->assertOk()->assertJson(['id' => $first->json('id'), 'replayed' => true]);
        $this->postJson('/performnce-upload', array_merge($payload, ['remark' => 'Changed']))->assertStatus(409);
        $this->postJson('/performnce-upload', array_merge($payload, ['password' => 'wrong']))->assertStatus(403);
        $this->assertDatabaseCount('performances', 1);
        $this->assertDatabaseCount('integration_outbox', 1);
        $event = IntegrationOutbox::firstOrFail();
        $this->assertSame('performance.v1', $event->payload['schema']);
        $this->assertCount(7, $event->payload['values'][0]);
        $this->assertStringNotContainsString('01/01/1990', json_encode($event->getAttributes()));
    }

    public function test_admin_id_inputs_export_the_same_canonical_marklist_schema(): void
    {
        $payload = $this->resultPayload();
        $this->postJson('/upload-result', $payload)->assertOk();
        $teacher = Teacher::create(['teacher_name' => 'QA Teacher', 'teacher_code' => 'T-QA', 'status' => 'Available✅']);
        $student = Students::create(['student_name' => 'QA Student', 'student_code' => 'S-QA', 'status' => 'OnGoing ✅']);
        $payload['teacher_name'] = $teacher->id;
        $payload['student_name'] = $student->id;
        $payload['submission_key'] = (string) Str::uuid();
        unset($payload['password'], $payload['student_code']);
        $this->actingAs($this->createAdmin());
        $this->postJson('/admin/marklist', $payload)->assertOk();
        $this->postJson('/admin/marklist', $payload)->assertOk()->assertJson(['replayed' => true]);
        $events = IntegrationOutbox::orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame(array_slice($events[0]->payload['values'][0], 0, 11), array_slice($events[1]->payload['values'][0], 0, 11));
        $this->assertDatabaseHas('marklists', ['teacher_name' => 'QA Teacher', 'student_name' => 'QA Student', 'student_code' => 'S-QA']);
    }

    public function test_snapshot_id_reuse_does_not_relabel_a_new_admin_result(): void
    {
        $payload = $this->resultPayload();
        $teacher = Teacher::create(['teacher_name' => 'QA Teacher', 'teacher_code' => 'T-QA']);
        $student = Students::create(['student_name' => 'QA Student', 'student_code' => 'S-QA']);
        $payload['teacher_name'] = $teacher->id;
        $payload['student_name'] = $student->id;
        $this->actingAs($this->createAdmin());
        $this->postJson('/admin/marklist', $payload)->assertOk();
        $teacher->update(['teacher_name' => 'Different teacher', 'teacher_code' => 'T-NEW']);
        $student->update(['student_name' => 'Different student', 'student_code' => 'S-NEW']);
        $this->postJson('/admin/marklist', $payload)->assertOk()->assertJson(['replayed' => true]);
        $this->assertDatabaseCount('marklists', 1);
        $record = Marklist::firstOrFail();
        $this->assertSame('QA Teacher', $record->teacher_name);
        $this->assertSame('QA Student', $record->student_name);
        $response = $this->getJson('/admin/marklist', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertSame('QA Teacher', $response->json('data.0.teacher_name'));
        $this->assertSame('QA Student', $response->json('data.0.student_name'));
        $query = ['draw'=>1,'start'=>0,'length'=>10,'search'=>['value'=>'QA','regex'=>false],
            'order'=>[['column'=>0,'dir'=>'asc']],
            'columns'=>array_map(function ($name) { return ['data'=>$name,'name'=>$name,'searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>false]]; }, ['teacher_name','teacher_code','student_name','subject_name'])];
        $searched = $this->getJson('/admin/marklist?'.http_build_query($query), ['X-Requested-With'=>'XMLHttpRequest'])->assertOk();
        $this->assertSame(1, (int)$searched->json('recordsFiltered'));
        $this->assertSame('QA Teacher', $searched->json('data.0.teacher_name'));
        foreach ([1,2,3] as $column) {
            $query['order'][0]['column']=$column;
            $query['order'][0]['dir']='desc';
            $this->getJson('/admin/marklist?'.http_build_query($query), ['X-Requested-With'=>'XMLHttpRequest'])->assertOk();
        }

    }

    public function test_student_lookup_requires_verification_and_omits_credentials_and_rates(): void
    {
        $this->createMaster();
        $this->postJson('/studentinforesult', ['teacher_name' => 'QA Teacher'])->assertStatus(422);
        $this->postJson('/studentinforesult', ['teacher_name' => 'QA Teacher', 'password' => 'wrong'])->assertStatus(403);
        $response = $this->postJson('/studentinforesult', ['teacher_name' => 'QA Teacher', 'password' => '01/01/1990'])->assertOk();
        $this->assertSame(['id', 'student_name', 'student_code'], array_keys($response->json('students.0')));
        $this->assertStringNotContainsString('teacher_dob', $response->getContent());
        $this->assertStringNotContainsString('hour_payment', $response->getContent());
    }
    public function test_valid_credential_change_does_not_change_a_saved_request_identity(): void
    {
        $payload = $this->resultPayload();
        $first = $this->postJson('/upload-result', $payload)->assertOk();
        $hash = IntegrationOutbox::firstOrFail()->payload_hash;
        \App\Models\Master::query()->update(['teacher_dob' => '02/02/1991']);
        $payload['password'] = '02/02/1991';
        $this->postJson('/upload-result', $payload)->assertOk()->assertJson(['id' => $first->json('id'), 'replayed' => true]);
        $this->assertSame($hash, IntegrationOutbox::firstOrFail()->payload_hash);
        $this->assertDatabaseCount('marklists', 1);
    }

    public function test_legacy_public_forms_render_submission_keys_and_verified_lookup_controls(): void
    {
        $this->createMaster();
        $this->createSubject();
        foreach (['/resultform', '/performance'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertSee('name="submission_key"', false);
            $response->assertSee('Verify and load students');
            $response->assertSee('type="password"', false);
            $response->assertDontSee('01/01/1990');
            $response->assertDontSee('QA Student');
        }
    }

    public function test_replay_survives_academic_reference_renames_or_removal(): void
    {
        $payload = $this->resultPayload();
        $this->postJson('/upload-result', $payload)->assertOk();
        \App\Models\Subject::whereKey($payload['subject'])->delete();
        $this->postJson('/upload-result', $payload)->assertOk()->assertJson(['replayed' => true]);
        $topic = Topic::create(['name' => 'Original topic']);
        $payload['submission_key'] = (string) Str::uuid();
        $payload['topic'] = $topic->id;
        $this->postJson('/performnce-upload', $payload)->assertOk();
        $topic->update(['name' => 'Renamed topic']);
        $this->postJson('/performnce-upload', $payload)->assertOk()->assertJson(['replayed' => true]);
        $topic->delete();
        $this->postJson('/performnce-upload', $payload)->assertOk()->assertJson(['replayed' => true]);
        $this->assertDatabaseCount('marklists', 1);
        $this->assertDatabaseCount('performances', 1);
        $this->assertDatabaseCount('integration_outbox', 2);
    }

}

