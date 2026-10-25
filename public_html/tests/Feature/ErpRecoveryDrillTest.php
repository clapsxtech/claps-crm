<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ErpRecoveryDrillTest extends TestCase
{
    public function test_isolated_recovery_rehearsal_passes(): void
    {
        $process = new Process([PHP_BINARY, __DIR__.'/../Integration/erp-recovery-drill.php']);
        $process->setTimeout(120);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $report['status']);
        $this->assertSame('synthetic_sqlite_only', $report['scope']);
        foreach (['corrupt_backup_rejected_before_restore', 'all_table_schema_and_rows_preserved',
            'receipt_replay_does_not_duplicate_ledger_or_audit', 'existing_destination_cannot_be_overwritten', 'original_synthetic_source_unchanged'] as $check) {
            $this->assertContains($check, $report['checks']);
        }
    }

    public function test_rehearsal_refuses_user_supplied_database_or_backup_paths(): void
    {
        $process = new Process([PHP_BINARY, __DIR__.'/../Integration/erp-recovery-drill.php', '/some/database.sqlite']);
        $process->run();
        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('no arguments', $process->getErrorOutput());
    }
}
