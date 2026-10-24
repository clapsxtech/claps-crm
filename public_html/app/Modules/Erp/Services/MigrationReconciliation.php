<?php
namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Compares an approved normalized snapshot with its persisted provenance. Never repairs data. */
class MigrationReconciliation
{
    private const TABLES = ['student'=>'erp_students','teacher'=>'erp_teachers','assignment'=>'erp_enrollments','eligibility'=>'erp_eligibilities','opening_balance'=>'erp_ledger','historical_attendance'=>'erp_migration_history'];

    public function verify(array $rows, array $references, array $expected, $batch, bool $lock = false): array
    {
        $digest = hash('sha256', self::canonicalJson(['rows'=>$rows, 'references'=>$references]));
        if (!$batch || $batch->source_id !== $references['source_manifest']['source_id'] || $batch->cutoff !== $references['cutoff_date'] || !hash_equals($digest, $batch->digest)) $this->fail('matching_committed_batch_required');
        foreach (['manifest'=>$references['source_manifest'], 'counts'=>$expected['counts'], 'totals'=>$expected['totals']] as $field=>$value) {
            try { $stored = json_decode($batch->$field, true, 64, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { $this->fail('batch_provenance_mismatch'); }
            if (!is_array($stored) || self::canonicalJson($stored) !== self::canonicalJson($value)) $this->fail('batch_provenance_mismatch');
        }
        $all = array_merge($rows, array_map(function ($row) { return $row + ['type'=>'eligibility']; }, $references['teacher_eligibilities']));
        $counts = array_fill_keys(array_keys($expected['counts']), 0);
        $totals = ['student_balance_paise'=>0, 'teacher_payable_paise'=>0, 'historical_financial_effect_paise'=>0];
        $mappingIds = []; $minutes = 0;
        foreach ($all as $row) {
            $mapping = $this->mapping($references['source_manifest']['source_id'], $row['type'], $row['external_id'], $lock);
            $target = $this->checkRow($row, $references, $mapping, $lock);
            $mappingIds[] = (int) $mapping->id;
            ++$counts[$row['type']];
            if ($row['type'] === 'opening_balance') {
                $field = $row['owner_type'] === 'student' ? 'student_balance_paise' : 'teacher_payable_paise';
                $sum = $totals[$field] + (int) $target->amount_paise;
                if (!is_int($sum)) $this->fail('reconciliation_total_overflow');
                $totals[$field] = $sum;
            }
            if ($row['type'] === 'historical_attendance') $minutes += (int) $target->actual_minutes;
        }
        // A batch may reuse older mappings, but must never claim extra, unreviewed ones.
        $query = DB::table('erp_migration_mappings')->where('batch_id', $batch->id)->orderBy('id');
        if ($lock) $query->lockForUpdate();
        foreach ($query->get() as $mapping) if (!in_array((int) $mapping->id, $mappingIds, true)) $this->fail('unexpected_batch_mapping');
        if (self::canonicalJson($counts) !== self::canonicalJson($expected['counts']) || self::canonicalJson($totals) !== self::canonicalJson($expected['totals'])) $this->fail('reconciliation_totals_mismatch');
        return ['reconciled'=>true, 'batch_id'=>(int) $batch->id, 'digest'=>$digest, 'counts'=>$counts, 'totals'=>$totals, 'historical_minutes'=>$minutes, 'mapped_records'=>count($mappingIds)];
    }

    /** Also used before reusing any previously mapped row, not just exact batch replay. */
    public function checkRow(array $row, array $references, $mapping, bool $lock = false)
    {
        $type = $row['type'];
        $source = $references['source_manifest']['source_id'];
        $hashRow = $row; if ($type === 'eligibility') unset($hashRow['type']);
        if (!$mapping || $mapping->source_id !== $source || $mapping->entity_type !== $type || $mapping->external_id !== $row['external_id'] || $mapping->target_type !== self::TABLES[$type] || !hash_equals(hash('sha256', self::canonicalJson($hashRow)), $mapping->row_digest)) $this->fail('existing_mapping_conflict');
        $batchQuery = DB::table('erp_migration_batches')->where('id', $mapping->batch_id);
        if ($lock) $batchQuery->lockForUpdate();
        $batch = $batchQuery->first();
        $cutoff = $batch ? \DateTimeImmutable::createFromFormat('!Y-m-d', $batch->cutoff) : false;
        if (!$batch || $batch->source_id !== $source || !$cutoff || $cutoff->format('Y-m-d') !== $batch->cutoff || $batch->cutoff > $references['cutoff_date'] || !preg_match('/\A[0-9a-f]{64}\z/', $batch->digest)) $this->fail('mapping_batch_provenance_mismatch');
        $query = DB::table(self::TABLES[$type])->where('id', $mapping->target_id);
        if ($lock) $query->lockForUpdate();
        $target = $query->first();
        if (!$target) $this->fail('mapped_target_missing');
        $unit = $type === 'eligibility' ? null : (int) DB::table('erp_units')->where('code', $type === 'teacher' ? $row['unit_codes'][0] : $row['unit_code'])->value('id');
        $values = [];
        if ($type === 'student' || $type === 'teacher') {
            foreach (['name','timezone','status'] as $field) $values[$field] = $row[$field];
            $values['code'] = $row['external_id'];
            if ($type === 'student') {
                $values['unit_id'] = $unit;
                foreach (['registered_on','grade','syllabus'] as $field) $values[$field] = $row[$field];
            } else {
                $units = DB::table('erp_teacher_units')->where('teacher_id', $target->id)->orderBy('id');
                if ($lock) $units->lockForUpdate();
                $units = $units->get();
                if ($units->count() !== 1 || (int) $units->first()->unit_id !== $unit) $this->fail('mapped_target_unit_drift');
            }
        } elseif ($type === 'assignment' || $type === 'eligibility') {
            $values = ['teacher_id'=>$this->mappedId($source, 'teacher', $row['teacher_id'], $lock), 'subject_id'=>$references['subject_ids'][$row['subject_code']]];
            foreach ($type === 'eligibility' ? ['grade','syllabus'] : ['starts_on','student_rate_paise','teacher_rate_paise','currency','status'] as $field) $values[$field] = $row[$field];
            if ($type === 'assignment') $values += ['unit_id'=>$unit, 'student_id'=>$this->mappedId($source, 'student', $row['student_id'], $lock), 'ends_on'=>$row['ends_on'] ?? null];
        } elseif ($type === 'opening_balance') {
            $event = 'migration:opening:'.hash('sha256', $source.'|'.$row['external_id']);
            $values = ['unit_id'=>$unit, 'student_id'=>null, 'teacher_id'=>null, 'attendance_id'=>null, 'reverses_id'=>null, 'kind'=>'opening_balance', 'currency'=>'INR', 'amount_paise'=>$row['amount_paise'], 'occurred_at'=>$row['as_of'].' 00:00:00', 'reference'=>'MIGRATION:'.$event, 'event_key'=>$event, 'created_by'=>(int) $batch->actor_id];
            $values[$row['owner_type'].'_id'] = $this->mappedId($source, $row['owner_type'], $row['owner_id'], $lock);
            if ($batch->cutoff !== $row['as_of']) $this->fail('mapping_batch_provenance_mismatch');
            $reversals = DB::table('erp_ledger')->where('reverses_id', $target->id); if ($lock) $reversals->lockForUpdate();
            if ($reversals->first()) $this->fail('opening_snapshot_has_reversal_requires_review');
        } else {
            $values = ['unit_id'=>$unit, 'batch_id'=>(int) $mapping->batch_id, 'enrollment_id'=>$this->mappedId($source, 'assignment', $row['assignment_id'], $lock), 'external_id'=>$row['external_id'], 'starts_at'=>str_replace(['T','Z'], [' ',''], $row['starts_at']), 'actual_minutes'=>$row['actual_minutes'], 'status'=>$row['status'], 'financial_effect'=>'none'];
        }
        foreach ($values as $field=>$expected) {
            $actual = $target->$field;
            if (is_int($expected)) {
                // PDO may return BIGINT values as strings. Do not coerce null, decimals or booleans.
                if ((!is_int($actual) && (!is_string($actual) || !preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $actual))) || (string) $expected !== (string) $actual) $this->fail('mapped_target_drift');
            } elseif ($actual !== $expected) $this->fail('mapped_target_drift');
        }
        return $target;
    }

    private function mapping(string $source, string $type, string $externalId, bool $lock)
    {
        $query = DB::table('erp_migration_mappings')->where('source_id', $source)->where('entity_type', $type)->where('external_id', $externalId);
        if ($lock) $query->lockForUpdate();
        $mapping = $query->first();
        if (!$mapping) $this->fail('mapped_source_missing');
        return $mapping;
    }

    private function mappedId(string $source, string $type, string $externalId, bool $lock): int
    {
        $mapping = $this->mapping($source, $type, $externalId, $lock);
        if ($mapping->target_type !== self::TABLES[$type]) $this->fail('existing_mapping_conflict');
        return (int) $mapping->target_id;
    }

    public static function canonicalJson(array $value): string
    {
        $normalize = function ($item) use (&$normalize) {
            if (!is_array($item)) return $item;
            if ($item && array_keys($item) !== range(0, count($item) - 1)) ksort($item, SORT_STRING);
            return array_map($normalize, $item);
        };
        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function fail(string $code): void { throw ValidationException::withMessages(['migration'=>$code]); }
}
