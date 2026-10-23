<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\MigrationPreview;
use PHPUnit\Framework\TestCase;

class ErpMigrationPreviewTest extends TestCase
{
    private function rows(): array
    {
        return [
            ['type' => 'student', 'external_id' => 'S-1', 'unit_code' => 'U-1', 'name' => 'Synthetic Student', 'timezone' => 'Asia/Kolkata', 'registered_on' => '2026-01-01', 'grade' => '8', 'syllabus' => 'Test'],
            ['type' => 'teacher', 'external_id' => 'T-1', 'unit_codes' => ['U-1'], 'name' => 'Synthetic Teacher', 'timezone' => 'Asia/Kolkata'],
            ['type' => 'assignment', 'external_id' => 'A-1', 'unit_code' => 'U-1', 'student_id' => 'S-1', 'teacher_id' => 'T-1', 'subject_code' => 'MATH', 'starts_on' => '2026-01-01', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR'],
            ['type' => 'opening_balance', 'external_id' => 'B-1', 'unit_code' => 'U-1', 'owner_type' => 'student', 'owner_id' => 'S-1', 'amount_paise' => 25000, 'currency' => 'INR', 'as_of' => '2026-10-01'],
            ['type' => 'historical_attendance', 'external_id' => 'H-1', 'unit_code' => 'U-1', 'assignment_id' => 'A-1', 'starts_at' => '2026-09-30T10:00:00Z', 'actual_minutes' => 60, 'status' => 'delivered', 'financial_effect' => 'none'],
        ];
    }

    private function preview(array $rows): array
    {
        return (new MigrationPreview())->preview($rows, ['unit_codes' => ['U-1', 'U-2'], 'subject_codes' => ['MATH'], 'cutoff_date' => '2026-10-01', 'source_manifest' => ['source_id' => 'SYN-EXPORT', 'exported_at' => '2026-10-01T00:00:00Z']]);
    }

    public function test_valid_batch_is_deterministic_read_only_and_never_replays_history(): void
    {
        $rows = $this->rows();
        $result = $this->preview($rows);
        $this->assertTrue($result['valid']);
        $this->assertTrue($result['dry_run']);
        $this->assertFalse($result['can_import']);
        $this->assertSame(0, $result['writes']);
        $this->assertSame(25000, $result['totals']['student_balance_paise']);
        $this->assertSame(0, $result['totals']['historical_financial_effect_paise']);
        $this->assertSame(1, $result['counts']['historical_attendance']);
        $this->assertSame($result, $this->preview($rows));
        $this->assertSame($this->rows(), $rows);
        $this->assertTrue($this->preview(array_reverse($rows))['valid']);
    }

    public function test_duplicate_ids_and_missing_references_block_totals(): void
    {
        $rows = $this->rows();
        $rows[] = $rows[0];
        $rows[2]['teacher_id'] = 'MISSING';
        $result = $this->preview($rows);
        $this->assertFalse($result['valid']);
        $this->assertNull($result['totals']);
        $codes = array_column($result['issues'], 'code');
        $this->assertContains('duplicate_stable_id', $codes);
        $this->assertContains('ambiguous_reference', $codes);
        $this->assertContains('missing_reference', $codes);
    }

    public function test_integer_paise_only_and_signed_opening_balances(): void
    {
        foreach (['25000', 25000.0, true, null] as $amount) {
            $rows = $this->rows(); $rows[3]['amount_paise'] = $amount;
            $this->assertContains('invalid_integer_paise', array_column($this->preview($rows)['issues'], 'code'));
        }
        $rows = $this->rows(); $rows[3]['amount_paise'] = -25000;
        $this->assertSame(-25000, $this->preview($rows)['totals']['student_balance_paise']);
    }

    public function test_historical_financial_flags_and_duplicate_sessions_are_blocked(): void
    {
        $rows = $this->rows();
        $rows[4]['financial_effect'] = 'post';
        $rows[4]['student_charge_paise'] = 12000;
        $rows[] = array_merge($rows[4], ['external_id' => 'H-2']);
        $codes = array_column($this->preview($rows)['issues'], 'code');
        $this->assertContains('historical_replay_forbidden', $codes);
        $this->assertContains('duplicate_historical_session', $codes);
    }

    public function test_cross_unit_references_and_duplicate_balances_are_blocked(): void
    {
        $rows = $this->rows(); $rows[2]['unit_code'] = 'U-2';
        $rows[] = array_merge($rows[3], ['external_id' => 'B-2']);
        $codes = array_column($this->preview($rows)['issues'], 'code');
        $this->assertContains('cross_unit_reference', $codes);
        $this->assertContains('duplicate_opening_balance', $codes);
    }

    public function test_malformed_rows_dates_and_missing_reference_catalog_fail_closed(): void
    {
        $rows = $this->rows();
        $rows[0]['external_id'] = 1;
        $rows[0]['registered_on'] = '2026-02-30';
        $rows[4]['starts_at'] = '2026-09-30 10:00:00';
        $rows[] = 'bad';
        $codes = array_column($this->preview($rows)['issues'], 'code');
        foreach (['invalid_stable_id', 'invalid_date', 'invalid_utc_timestamp', 'unknown_row_type'] as $code) $this->assertContains($code, $codes);
        $this->assertFalse((new MigrationPreview())->preview($this->rows())['valid']);
    }

    public function test_total_overflow_is_blocked(): void
    {
        $rows = $this->rows(); $rows[3]['amount_paise'] = PHP_INT_MAX;
        $rows[] = array_merge($rows[0], ['external_id' => 'S-2']);
        $rows[] = array_merge($rows[3], ['external_id' => 'B-2', 'owner_id' => 'S-2', 'amount_paise' => 1]);
        $this->assertContains('total_overflow', array_column($this->preview($rows)['issues'], 'code'));
    }
    public function test_cutoff_manifest_unit_pool_and_interval_constraints(): void
    {
        $rows = $this->rows();
        $rows[1]['unit_codes'] = ['U-1', 'U-2'];
        $rows[2]['ends_on'] = '2026-09-01';
        $rows[3]['as_of'] = '2026-10-02';
        $rows[] = array_merge($rows[2], ['external_id' => 'A-2', 'starts_on' => '2026-08-01']);
        $codes = array_column($this->preview($rows)['issues'], 'code');
        foreach (['invalid_unit_list', 'outside_assignment_dates', 'inconsistent_cutoff', 'overlapping_student_subject_assignment'] as $code) $this->assertContains($code, $codes);
        $codes = array_column((new MigrationPreview())->preview($this->rows())['issues'], 'code');
        $this->assertContains('required_valid_cutoff', $codes);
        $this->assertContains('required_source_manifest', $codes);
    }

}
