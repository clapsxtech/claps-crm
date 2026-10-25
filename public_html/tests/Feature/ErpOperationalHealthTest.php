<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\OperationalHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ErpOperationalHealthTest extends TestCase
{
    private function event(string $status, array $attributes = []): void
    {
        DB::table('integration_outbox')->insert(array_merge([
            'submission_key' => (string) \Illuminate\Support\Str::uuid(), 'payload_hash' => str_repeat('a', 64),
            'event_type' => 'synthetic', 'payload' => '{"private":"never disclose this"}',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    public function test_empty_audit_is_informational_and_healthy_command_returns_zero(): void
    {
        $report = (new OperationalHealth())->inspect();
        $this->assertSame('ok', $report['status']);
        $this->assertSame(0, $report['checks']['audit']['events_last_24_hours']);
        $this->assertNull($report['checks']['audit']['latest_event_at']);
        $this->assertSame(0, Artisan::call('erp:health'));
        $this->assertSame('ok', json_decode(Artisan::output(), true)['status']);
    }

    public function test_uncertain_stale_unknown_and_old_pending_are_reported_without_mutation(): void
    {
        $this->event('uncertain');
        $this->event('sending', ['claimed_at' => now()->subHour()]);
        $this->event('sending');
        $this->event('sending', ['claimed_at' => now()]);
        $this->event('pending', ['created_at' => now()->subHour()]);
        $this->event('pending');
        $this->event('unexpected');
        $before = DB::table('integration_outbox')->orderBy('id')->get()->toJson();
        $report = (new OperationalHealth())->inspect();
        $this->assertSame('warning', $report['status']);
        $outbox = $report['checks']['outbox'];
        $this->assertSame(1, $outbox['uncertain']);
        $this->assertSame(2, $outbox['stale_claims']);
        $this->assertSame(1, $outbox['old_pending']);
        $this->assertSame(1, $outbox['unknown_status']);
        $this->assertFalse($outbox['delivery_enabled']);
        $this->assertSame($before, DB::table('integration_outbox')->orderBy('id')->get()->toJson());
        $this->assertStringNotContainsString('never disclose', json_encode($report));
        $this->assertSame(1, Artisan::call('erp:health'));
    }

    public function test_failed_job_and_audit_indicators_disclose_counts_not_content(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'synthetic-failure', 'connection' => 'synthetic', 'queue' => 'synthetic', 'payload' => 'private-job', 'exception' => 'private-error', 'failed_at' => now()]);
        DB::table('erp_audit')->insert(['action' => 'synthetic', 'entity_type' => 'test', 'payload' => 'private-audit', 'created_at' => now(), 'updated_at' => now()]);
        $report = (new OperationalHealth())->inspect();
        $this->assertSame('warning', $report['status']);
        $this->assertSame(1, $report['checks']['failed_jobs']['count']);
        $this->assertSame(1, $report['checks']['audit']['events_last_24_hours']);
        $this->assertStringNotContainsString('private-', json_encode($report));
    }

    public function test_pending_migration_is_an_error_and_other_checks_still_run(): void
    {
        DB::table('migrations')->where('migration', '2026_10_23_130000_create_erp_core_tables')->delete();
        $report = (new OperationalHealth())->inspect();
        $this->assertSame('error', $report['status']);
        $this->assertSame(1, $report['checks']['schema']['pending_migrations']);
        $this->assertSame('ok', $report['checks']['audit']['status']);
        $this->assertSame(2, Artisan::call('erp:health'));
    }

    public function test_unavailable_check_redacts_error_and_does_not_claim_health(): void
    {
        $original = DB::getFacadeRoot();
        try {
            DB::swap(\Mockery::mock()->shouldReceive('connection')->once()->andThrow(new \RuntimeException('secret-host-password'))->getMock());
            $report = (new OperationalHealth())->inspect();
        } finally {
            DB::swap($original);
        }
        $this->assertSame('error', $report['status']);
        $this->assertSame('database_unavailable', $report['checks']['database']['code']);
        $this->assertStringNotContainsString('secret', json_encode($report));
    }

    public function test_missing_required_tables_are_not_reported_as_healthy(): void
    {
        $original = Schema::getFacadeRoot();
        try {
            Schema::swap(\Mockery::mock()->shouldReceive('hasTable')->andReturn(false)->getMock());
            $report = (new OperationalHealth())->inspect();
        } finally { Schema::swap($original); }
        $this->assertSame('error', $report['status']);
        $this->assertSame(6, $report['checks']['schema']['missing_required_tables']);
        $this->assertSame('ok', $report['checks']['audit']['status']);
    }

    public function test_one_failed_indicator_does_not_hide_other_indicators_or_leak_errors(): void
    {
        $original = Schema::getFacadeRoot();
        try {
            Schema::swap(\Mockery::mock()->shouldReceive('hasTable')->andThrow(new \RuntimeException('secret-schema-detail'))->getMock());
            $report = (new OperationalHealth())->inspect();
        } finally { Schema::swap($original); }
        $this->assertSame('error', $report['status']);
        $this->assertSame('check_unavailable', $report['checks']['schema']['code']);
        $this->assertSame('ok', $report['checks']['outbox']['status']);
        $this->assertStringNotContainsString('secret', json_encode($report));
    }

    public function test_invalid_threshold_is_rejected(): void
    {
        foreach (['0', '-1', '10081', '1.5', 'bad'] as $value) {
            $this->assertSame(2, Artisan::call('erp:health', ['--stale-minutes' => $value]));
            $this->assertSame('invalid_stale_minutes', json_decode(Artisan::output(), true)['code']);
        }
    }
}
