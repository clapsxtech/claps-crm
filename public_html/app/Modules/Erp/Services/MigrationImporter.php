<?php
namespace App\Modules\Erp\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Guarded import of approved snapshots. Never invokes live attendance or payroll services. */
class MigrationImporter
{
    private const FIELDS = [
        'student' => ['type','external_id','unit_code','name','timezone','registered_on','grade','syllabus','status'],
        'teacher' => ['type','external_id','unit_codes','name','timezone','status'],
        'assignment' => ['type','external_id','unit_code','student_id','teacher_id','subject_code','starts_on','ends_on','student_rate_paise','teacher_rate_paise','currency','status'],
        'opening_balance' => ['type','external_id','unit_code','owner_type','owner_id','amount_paise','currency','as_of'],
        'historical_attendance' => ['type','external_id','unit_code','assignment_id','starts_at','actual_minutes','status','financial_effect'],
    ];

    public function preview(array $rows, array $references): array
    {
        try {
            $this->structure($rows, $references);
            $report = (new MigrationPreview())->preview($rows, $references);
            $report['digest'] = $this->digest(['rows' => $rows, 'references' => $references]);
            $report['schema_version'] = $references['schema_version'];
            if ($report['valid']) {
                $this->eligibilities($rows, $references);
                $this->destination($rows, $references, false);
                $report['counts']['eligibility'] = count($references['teacher_eligibilities']);
                $batch = DB::table('erp_migration_batches')->where('source_id', $references['source_manifest']['source_id'])->where('cutoff', $references['cutoff_date'])->first();
                if ($batch) $report['reconciliation'] = (new MigrationReconciliation())->verify($rows, $references, $report, $batch);
            }
            return $report;
        } catch (ValidationException $e) {
            return $this->invalid(array_values($e->errors())[0][0]);
        } catch (\Throwable $e) {
            return $this->invalid('invalid_input_or_unavailable_destination');
        }
    }

    /** Read-only exact-snapshot check, not a current cash/payroll balance certification. */
    public function reconcile(array $rows, array $references): array
    {
        return DB::transaction(function () use ($rows, $references) {
            $report = $this->preview($rows, $references);
            if (!$report['valid']) return ['mode'=>'reconcile', 'reconciled'=>false] + $report;
            if (!isset($report['reconciliation'])) return ['mode'=>'reconcile', 'reconciled'=>false] + $this->invalid('matching_committed_batch_required');
            return ['mode'=>'reconcile', 'reconciled'=>true] + $report;
        });
    }

    /** Exercises the real import and reconciliation, then rolls back even on success. */
    public function rehearse(array $rows, array $references, string $expectedDigest, string $approvedCutoff, int $actorId): array
    {
        if (app()->environment('production')) $this->fail('production_rehearsal_forbidden');
        if (config('integrations.sheets.enabled', false) || config('google.service.enable', false)) $this->fail('rehearsal_requires_disabled_integrations');
        $connection = DB::connection();
        if (!in_array($connection->getDriverName(), ['sqlite', 'mysql'], true)) $this->fail('rehearsal_transaction_support_unverified');
        if ($connection->getDriverName() === 'mysql') {
            $tables = ['erp_students','erp_teachers','erp_teacher_units','erp_eligibilities','erp_enrollments','erp_ledger','erp_migration_batches','erp_migration_mappings','erp_migration_history','erp_audit'];
            $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', $connection->getDatabaseName())->whereIn('TABLE_NAME', $tables)->pluck('ENGINE');
            if ($engines->count() !== count($tables) || $engines->contains(function ($engine) { return strtoupper($engine) !== 'INNODB'; })) $this->fail('rehearsal_requires_transactional_tables');
        }
        $level = $connection->transactionLevel();
        $connection->beginTransaction();
        try {
            $result = $this->execute($rows, $references, $expectedDigest, $approvedCutoff, $actorId);
        } finally {
            // Preserve an enclosing test/caller transaction while discarding this rehearsal/savepoint.
            if ($connection->transactionLevel() > $level) $connection->rollBack($level);
        }
        $reconciliation = $result['reconciliation'];
        unset($reconciliation['batch_id']); // A rolled-back ID must never look like a committed batch.
        return ['dry_run'=>true, 'can_import'=>false, 'valid'=>true, 'rehearsed'=>true, 'rolled_back'=>true, 'replayed'=>$result['replayed'], 'writes'=>0, 'attempted_writes'=>$result['writes'], 'digest'=>$result['digest'], 'counts'=>$result['counts'], 'totals'=>$result['totals'], 'reconciliation'=>$reconciliation];
    }

    public function execute(array $rows, array $references, string $expectedDigest, string $approvedCutoff, int $actorId, bool $allowProduction = false): array
    {
        if (config('erp.migration_import_enabled', false) !== true) $this->fail('import_disabled');
        if (app()->environment('production') && !$allowProduction) $this->fail('production_opt_in_required');
        $this->structure($rows, $references);
        $digest = $this->digest(['rows' => $rows, 'references' => $references]);
        if (!preg_match('/\A[0-9a-f]{64}\z/', $expectedDigest) || !hash_equals($digest, $expectedDigest)) $this->fail('digest_mismatch');
        if ($approvedCutoff !== $references['cutoff_date']) $this->fail('cutoff_approval_mismatch');
        $report = (new MigrationPreview())->preview($rows, $references);
        if (!$report['valid']) $this->fail('preview_has_blocking_issues');
        $this->eligibilities($rows, $references);
        $report['counts']['eligibility'] = count($references['teacher_eligibilities']);
        return DB::transaction(function () use ($rows, $references, $digest, $actorId, $report) {
            $actor = User::query()->where('id', $actorId)->lockForUpdate()->first();
            if (!$actor) $this->fail('invalid_import_actor');
            $this->target($references);
            $unitCodes = $this->unitCodes($rows);
            $units = DB::table('erp_units')->whereIn('code', $unitCodes)->orderBy('id')->lockForUpdate()->get()->keyBy('code');
            if ($units->count() !== count($unitCodes) || array_diff($unitCodes, $units->keys()->all())) $this->fail('unknown_destination_unit');
            foreach ($units as $unit) {
                (new Access())->unit($actor, (int) $unit->id);
                $membership = DB::table('erp_memberships')->where('user_id', $actorId)->where('unit_id', $unit->id)->lockForUpdate()->first();
                if (!$membership || $membership->role !== 'admin') $this->fail('unit_admin_required');
            }
            $source = $references['source_manifest']['source_id'];
            $batch = DB::table('erp_migration_batches')->where('source_id', $source)->where('cutoff', $references['cutoff_date'])->lockForUpdate()->first();
            if ($batch) {
                if (!hash_equals($batch->digest, $digest)) $this->fail('source_cutoff_already_imported_with_different_digest');
                $this->destination($rows, $references, true);
                $reconciliation = (new MigrationReconciliation())->verify($rows, $references, $report, $batch, true);
                return ['reconciliation' => $reconciliation, 'dry_run' => false, 'replayed' => true, 'batch_id' => (int) $batch->id, 'digest' => $digest, 'counts' => json_decode($batch->counts, true), 'totals' => json_decode($batch->totals, true), 'writes' => 0];
            }
            $this->destination($rows, $references, true);
            $now = now()->format('Y-m-d H:i:s');
            $batchId = DB::table('erp_migration_batches')->insertGetId(['source_id' => $source, 'cutoff' => $references['cutoff_date'], 'digest' => $digest, 'manifest' => $this->json($references['source_manifest']), 'counts' => $this->json($report['counts']), 'totals' => $this->json($report['totals']), 'actor_id' => $actorId, 'created_at' => $now]);
            $ids = []; $writes = 1;
            foreach (['student','teacher','eligibility','assignment','opening_balance','historical_attendance'] as $type) {
                $typedRows = $type === 'eligibility' ? $references['teacher_eligibilities'] : array_values(array_filter($rows, function ($row) use ($type) { return $row['type'] === $type; }));
                foreach ($typedRows as $row) {
                    $mapping = DB::table('erp_migration_mappings')->where('source_id', $source)->where('entity_type', $type)->where('external_id', $row['external_id'])->lockForUpdate()->first();
                    if ($mapping) {
                        if (!hash_equals($mapping->row_digest, $this->digest($row)) || !DB::table($this->table($type))->where('id', $mapping->target_id)->lockForUpdate()->first()) $this->fail('existing_mapping_conflict');
                        $ids[$type][$row['external_id']] = (int) $mapping->target_id;
                        continue;
                    }
                    $unitId = $type === 'teacher' ? (int) $units[$row['unit_codes'][0]]->id : ($type === 'eligibility' ? null : (int) $units[$row['unit_code']]->id);
                    $values = [];
                    if ($type === 'student') {
                        $values = ['unit_id' => $unitId, 'code' => $row['external_id'], 'name' => $row['name'], 'timezone' => $row['timezone'], 'registered_on' => $row['registered_on'], 'grade' => $row['grade'], 'syllabus' => $row['syllabus'], 'status' => $row['status']];
                    } elseif ($type === 'teacher') {
                        $values = ['code' => $row['external_id'], 'name' => $row['name'], 'timezone' => $row['timezone'], 'status' => $row['status']];
                    } elseif ($type === 'eligibility') {
                        $values = ['teacher_id' => $ids['teacher'][$row['teacher_id']], 'subject_id' => $references['subject_ids'][$row['subject_code']], 'grade' => $row['grade'], 'syllabus' => $row['syllabus']];
                    } elseif ($type === 'assignment') {
                        $values = ['unit_id' => $unitId, 'student_id' => $ids['student'][$row['student_id']], 'teacher_id' => $ids['teacher'][$row['teacher_id']], 'subject_id' => $references['subject_ids'][$row['subject_code']], 'starts_on' => $row['starts_on'], 'ends_on' => $row['ends_on'] ?? null, 'student_rate_paise' => $row['student_rate_paise'], 'teacher_rate_paise' => $row['teacher_rate_paise'], 'currency' => 'INR', 'status' => $row['status']];
                        $overlap = DB::table('erp_enrollments')->where('student_id', $values['student_id'])->where('subject_id', $values['subject_id'])->where('starts_on', '<=', $values['ends_on'] ?? '9999-12-31')->where(function ($q) use ($values) { $q->whereNull('ends_on')->orWhere('ends_on', '>=', $values['starts_on']); })->lockForUpdate()->first();
                        if ($overlap) $this->fail('destination_assignment_overlap');
                    } elseif ($type === 'opening_balance') {
                        $owner = $row['owner_type'];
                        $ownerId = $ids[$owner][$row['owner_id']];
                        DB::table($this->table($owner))->where('id', $ownerId)->lockForUpdate()->first();
                        if (DB::table('erp_ledger')->where($owner.'_id', $ownerId)->lockForUpdate()->first()) $this->fail('opening_balance_requires_empty_'.$owner.'_ledger');
                        $event = 'migration:opening:'.hash('sha256', $source.'|'.$row['external_id']);
                        $values = ['unit_id' => $unitId, 'student_id' => $owner === 'student' ? $ownerId : null, 'teacher_id' => $owner === 'teacher' ? $ownerId : null, 'attendance_id' => null, 'kind' => 'opening_balance', 'amount_paise' => $row['amount_paise'], 'currency' => 'INR', 'reference' => 'MIGRATION:'.$event, 'event_key' => $event, 'occurred_at' => $references['cutoff_date'].' 00:00:00', 'created_by' => $actorId];
                    } else {
                        $values = ['batch_id' => $batchId, 'unit_id' => $unitId, 'enrollment_id' => $ids['assignment'][$row['assignment_id']], 'external_id' => $row['external_id'], 'starts_at' => str_replace(['T','Z'], [' ',''], $row['starts_at']), 'actual_minutes' => $row['actual_minutes'], 'status' => $row['status'], 'financial_effect' => 'none', 'created_at' => $now];
                    }
                    if ($type !== 'historical_attendance') $values += ['created_at' => $now, 'updated_at' => $now];
                    $id = DB::table($this->table($type))->insertGetId($values); ++$writes;
                    if ($type === 'teacher') { DB::table('erp_teacher_units')->insert(['teacher_id' => $id, 'unit_id' => $unitId, 'created_at' => $now, 'updated_at' => $now]); ++$writes; }
                    DB::table('erp_migration_mappings')->insert(['source_id' => $source, 'entity_type' => $type, 'external_id' => $row['external_id'], 'target_type' => $this->table($type), 'target_id' => $id, 'row_digest' => $this->digest($row), 'batch_id' => $batchId]); ++$writes;
                    $ids[$type][$row['external_id']] = (int) $id;
                }
            }
            Audit::record(null, $actorId, 'migration.imported', 'migration_batch', $batchId, ['digest' => $digest, 'cutoff' => $references['cutoff_date'], 'counts' => $report['counts']]); ++$writes;
            $batch = DB::table('erp_migration_batches')->where('id', $batchId)->first();
            $reconciliation = (new MigrationReconciliation())->verify($rows, $references, $report, $batch, true);
            return ['reconciliation' => $reconciliation, 'dry_run' => false, 'replayed' => false, 'batch_id' => (int) $batchId, 'digest' => $digest, 'counts' => $report['counts'], 'totals' => $report['totals'], 'writes' => $writes];
        }, 3);
    }

    private function structure(array $rows, array $references): void
    {
        if (!$rows || count($rows) > 5000 || array_keys($rows) !== range(0, count($rows) - 1)) $this->fail('invalid_rows_list');
        if (!in_array($references['schema_version'] ?? null, [1, 2], true)) $this->fail('supported_schema_version_required');
        foreach (['unit_codes','subject_codes','subject_ids','subject_names','teacher_eligibilities'] as $key) if (!isset($references[$key]) || !is_array($references[$key]) || count($references[$key]) > 5000) $this->fail('invalid_reference_catalog');
        foreach (['unit_codes','subject_codes','teacher_eligibilities'] as $key) if ($references[$key] && array_keys($references[$key]) !== range(0, count($references[$key]) - 1)) $this->fail('reference_catalog_must_be_list');
        if (!is_string($references['target_database'] ?? null) || trim($references['target_database']) === '') $this->fail('explicit_target_database_required');
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['type'] ?? null) || !isset(self::FIELDS[$row['type']])) $this->fail('unknown_row_type');
            if (array_diff(array_keys($row), self::FIELDS[$row['type']])) $this->fail('unsupported_row_fields');
            foreach (['name','grade','syllabus'] as $key) if (isset($row[$key]) && (!is_string($row[$key]) || strlen($row[$key]) > ($key === 'name' ? 255 : 100))) $this->fail('invalid_text_length');
            if ($row['type'] === 'opening_balance' && ($row['owner_type'] ?? null) === 'teacher' && $references['schema_version'] !== 2) $this->fail('teacher_opening_requires_schema_version_2');
            if ($row['type'] === 'teacher' && (!is_array($row['unit_codes'] ?? null) || array_keys($row['unit_codes']) !== [0])) $this->fail('teacher_requires_one_unit_list');
            $statuses = ['student'=>['active','paused','stopped'],'teacher'=>['active','inactive'],'assignment'=>['active','ended']];
            if (isset($statuses[$row['type']]) && !in_array($row['status'] ?? null, $statuses[$row['type']], true)) $this->fail('explicit_status_required');
            if ($row['type'] === 'assignment') foreach (['student_rate_paise','teacher_rate_paise'] as $field) if (!is_int($row[$field] ?? null) || $row[$field] < 0 || $row[$field] > 100000000) $this->fail('rate_exceeds_operational_limit');
            if ($row['type'] === 'assignment' && $row['status'] === 'ended' && !isset($row['ends_on'])) $this->fail('ended_assignment_requires_end_date');
        }
        $openings = [];
        foreach ($rows as $row) if ($row['type'] === 'opening_balance' && is_string($row['owner_type'] ?? null) && is_string($row['owner_id'] ?? null)) $openings[$row['owner_type']][$row['owner_id']] = true;
        foreach ($rows as $row) {
            if ($row['type'] === 'student' && (!is_string($row['external_id'] ?? null) || !isset($openings['student'][$row['external_id']]))) $this->fail('explicit_student_opening_balance_required');
            if ($references['schema_version'] === 2 && $row['type'] === 'teacher' && (!is_string($row['external_id'] ?? null) || !isset($openings['teacher'][$row['external_id']]))) $this->fail('explicit_teacher_opening_balance_required');
        }
    }

    private function eligibilities(array $rows, array $references): void
    {
        $teachers = []; $students = []; $eligible = []; $ids = [];
        foreach ($rows as $row) { if ($row['type'] === 'teacher') $teachers[$row['external_id']] = $row; if ($row['type'] === 'student') $students[$row['external_id']] = $row; }
        foreach ($references['teacher_eligibilities'] as $entry) {
            if (!is_array($entry) || array_diff(array_keys($entry), ['external_id','teacher_id','subject_code','grade','syllabus'])) $this->fail('invalid_explicit_eligibility');
            foreach (['external_id','teacher_id','subject_code','grade','syllabus'] as $field) if (!is_string($entry[$field] ?? null) || trim($entry[$field]) === '' || strlen($entry[$field]) > 100) $this->fail('invalid_explicit_eligibility');
            if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]*\z/', $entry['external_id']) || isset($ids[$entry['external_id']])) $this->fail('duplicate_or_invalid_eligibility_id');
            $ids[$entry['external_id']] = true;
            if (!isset($teachers[$entry['teacher_id']]) || !in_array($entry['subject_code'], $references['subject_codes'], true)) $this->fail('eligibility_reference_missing');
            $key = $this->json([$entry['teacher_id'],$entry['subject_code'],$entry['grade'],$entry['syllabus']]);
            if (isset($eligible[$key])) $this->fail('duplicate_eligibility');
            $eligible[$key] = true;
        }
        foreach ($rows as $row) if ($row['type'] === 'assignment') {
            $student = $students[$row['student_id']];
            if (!isset($eligible[$this->json([$row['teacher_id'],$row['subject_code'],$student['grade'],$student['syllabus']])])) $this->fail('explicit_teacher_eligibility_required');
        }
    }

    private function destination(array $rows, array $references, bool $lock): void
    {
        $this->target($references);
        $source = $references['source_manifest']['source_id'];
        $codes = $this->unitCodes($rows);
        $foundCodes = DB::table('erp_units')->whereIn('code', $codes)->pluck('code')->all();
        if (count($foundCodes) !== count($codes) || array_diff($codes, $foundCodes)) $this->fail('unknown_destination_unit');
        foreach ($references['subject_codes'] as $code) {
            if (!is_string($code) || !is_int($references['subject_ids'][$code] ?? null) || !is_string($references['subject_names'][$code] ?? null)) $this->fail('explicit_subject_mapping_required');
            $query = DB::table('erp_subjects')->where('id', $references['subject_ids'][$code]); if ($lock) $query->lockForUpdate();
            $subject = $query->first();
            if (!$subject || $subject->name !== $references['subject_names'][$code]) $this->fail('subject_mapping_conflict');
        }
        foreach (array_merge($rows, array_map(function ($row) { return $row + ['type' => 'eligibility']; }, $references['teacher_eligibilities'])) as $row) {
            $type = $row['type'];
            $query = DB::table('erp_migration_mappings')->where('source_id', $source)->where('entity_type', $type)->where('external_id', $row['external_id']); if ($lock) $query->lockForUpdate();
            $mapping = $query->first();
            $hashRow = $row; if ($type === 'eligibility') unset($hashRow['type']);
            if ($mapping && (!hash_equals($mapping->row_digest, $this->digest($hashRow)) || $mapping->target_type !== $this->table($type))) $this->fail('existing_mapping_conflict');
            if (!$mapping && $type === 'opening_balance') {
                $owner = DB::table('erp_migration_mappings')->where('source_id', $source)->where('entity_type', $row['owner_type'])->where('external_id', $row['owner_id']); if ($lock) $owner->lockForUpdate();
                $owner = $owner->first();
                if ($owner) {
                    $ledger = DB::table('erp_ledger')->where($row['owner_type'].'_id', $owner->target_id); if ($lock) $ledger->lockForUpdate();
                    if ($ledger->first()) $this->fail('opening_balance_requires_empty_'.$row['owner_type'].'_ledger');
                }
            }
            if ($mapping) (new MigrationReconciliation())->checkRow($row, $references, $mapping, $lock);
            if (!$mapping && in_array($type, ['student','teacher'], true)) {
                $query = DB::table($this->table($type))->where('code', $row['external_id']); if ($lock) $query->lockForUpdate();
                if ($query->first()) $this->fail('unmanaged_stable_code_conflict');
            }
        }
        $query = DB::table('erp_migration_batches')->where('source_id', $source)->where('cutoff', $references['cutoff_date']); if ($lock) $query->lockForUpdate();
        $batch = $query->first();
        if ($batch && !hash_equals($batch->digest, $this->digest(['rows' => $rows, 'references' => $references]))) $this->fail('source_cutoff_already_imported_with_different_digest');
    }

    private function target(array $references): void
    {
        $connection = DB::connection();
        $database = $connection->getDriverName() === 'mysql' ? $connection->selectOne('SELECT DATABASE() AS name')->name : $connection->getDatabaseName();
        if ($database !== $references['target_database']) $this->fail('target_database_mismatch');
    }
    private function unitCodes(array $rows): array
    {
        $codes = []; foreach ($rows as $row) foreach ($row['type'] === 'teacher' ? $row['unit_codes'] : [$row['unit_code']] as $code) $codes[] = $code;
        return array_values(array_unique($codes));
    }
    private function table(string $type): string
    {
        return ['student'=>'erp_students','teacher'=>'erp_teachers','assignment'=>'erp_enrollments','eligibility'=>'erp_eligibilities','opening_balance'=>'erp_ledger','historical_attendance'=>'erp_migration_history'][$type];
    }
    private function digest(array $value): string { return hash('sha256', $this->json($value)); }
    private function json(array $value): string
    {
        return MigrationReconciliation::canonicalJson($value);
    }
    private function fail(string $code): void { throw ValidationException::withMessages(['migration' => $code]); }
    private function invalid(string $code): array
    {
        return ['dry_run'=>true,'can_import'=>false,'valid'=>false,'writes'=>0,'digest'=>null,'counts'=>[],'totals'=>null,'issues'=>[['row'=>0,'field'=>'migration','code'=>$code,'blocking'=>true]]];
    }
}
