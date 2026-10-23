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
            $report['schema_version'] = 1;
            if ($report['valid']) {
                $this->eligibilities($rows, $references);
                $this->destination($rows, $references, false);
                $report['counts']['eligibility'] = count($references['teacher_eligibilities']);
            }
            return $report;
        } catch (ValidationException $e) {
            return $this->invalid(array_values($e->errors())[0][0]);
        } catch (\Throwable $e) {
            return $this->invalid('invalid_input_or_unavailable_destination');
        }
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
                return ['dry_run' => false, 'replayed' => true, 'batch_id' => (int) $batch->id, 'digest' => $digest, 'counts' => json_decode($batch->counts, true), 'totals' => json_decode($batch->totals, true), 'writes' => 0];
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
                        $studentId = $ids['student'][$row['owner_id']];
                        DB::table('erp_students')->where('id', $studentId)->lockForUpdate()->first();
                        if (DB::table('erp_ledger')->where('student_id', $studentId)->lockForUpdate()->first()) $this->fail('opening_balance_requires_empty_student_ledger');
                        $event = 'migration:opening:'.hash('sha256', $source.'|'.$row['external_id']);
                        $values = ['unit_id' => $unitId, 'student_id' => $studentId, 'teacher_id' => null, 'attendance_id' => null, 'kind' => 'opening_balance', 'amount_paise' => $row['amount_paise'], 'currency' => 'INR', 'reference' => 'MIGRATION:'.$event, 'event_key' => $event, 'occurred_at' => $references['cutoff_date'].' 00:00:00', 'created_by' => $actorId];
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
            return ['dry_run' => false, 'replayed' => false, 'batch_id' => (int) $batchId, 'digest' => $digest, 'counts' => $report['counts'], 'totals' => $report['totals'], 'writes' => $writes];
        }, 3);
    }

    private function structure(array $rows, array $references): void
    {
        if (!$rows || count($rows) > 5000 || array_keys($rows) !== range(0, count($rows) - 1)) $this->fail('invalid_rows_list');
        if (($references['schema_version'] ?? null) !== 1) $this->fail('schema_version_1_required');
        foreach (['unit_codes','subject_codes','subject_ids','subject_names','teacher_eligibilities'] as $key) if (!isset($references[$key]) || !is_array($references[$key])) $this->fail('invalid_reference_catalog');
        foreach (['unit_codes','subject_codes','teacher_eligibilities'] as $key) if ($references[$key] && array_keys($references[$key]) !== range(0, count($references[$key]) - 1)) $this->fail('reference_catalog_must_be_list');
        if (!is_string($references['target_database'] ?? null) || trim($references['target_database']) === '') $this->fail('explicit_target_database_required');
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['type'] ?? null) || !isset(self::FIELDS[$row['type']])) $this->fail('unknown_row_type');
            if (array_diff(array_keys($row), self::FIELDS[$row['type']])) $this->fail('unsupported_row_fields');
            foreach (['name','grade','syllabus'] as $key) if (isset($row[$key]) && (!is_string($row[$key]) || strlen($row[$key]) > ($key === 'name' ? 255 : 100))) $this->fail('invalid_text_length');
            if ($row['type'] === 'opening_balance' && ($row['owner_type'] ?? null) !== 'student') $this->fail('teacher_opening_balance_not_supported');
            if ($row['type'] === 'teacher' && (!is_array($row['unit_codes'] ?? null) || array_keys($row['unit_codes']) !== [0])) $this->fail('teacher_requires_one_unit_list');
            $statuses = ['student'=>['active','paused','stopped'],'teacher'=>['active','inactive'],'assignment'=>['active','ended']];
            if (isset($statuses[$row['type']]) && !in_array($row['status'] ?? null, $statuses[$row['type']], true)) $this->fail('explicit_status_required');
            if ($row['type'] === 'assignment') foreach (['student_rate_paise','teacher_rate_paise'] as $field) if (!is_int($row[$field] ?? null) || $row[$field] < 0 || $row[$field] > 100000000) $this->fail('rate_exceeds_operational_limit');
            if ($row['type'] === 'assignment' && $row['status'] === 'ended' && !isset($row['ends_on'])) $this->fail('ended_assignment_requires_end_date');
        }
        $openings = []; foreach ($rows as $row) if ($row['type'] === 'opening_balance' && is_string($row['owner_id'] ?? null)) $openings[$row['owner_id']] = true;
        foreach ($rows as $row) if ($row['type'] === 'student' && (!is_string($row['external_id'] ?? null) || !isset($openings[$row['external_id']]))) $this->fail('explicit_student_opening_balance_required');
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
                $owner = DB::table('erp_migration_mappings')->where('source_id', $source)->where('entity_type', 'student')->where('external_id', $row['owner_id']); if ($lock) $owner->lockForUpdate();
                $owner = $owner->first();
                if ($owner) {
                    $ledger = DB::table('erp_ledger')->where('student_id', $owner->target_id); if ($lock) $ledger->lockForUpdate();
                    if ($ledger->first()) $this->fail('opening_balance_requires_empty_student_ledger');
                }
            }
            if (in_array($type, ['student','teacher'], true)) {
                $query = DB::table($this->table($type))->where('code', $row['external_id']); if ($lock) $query->lockForUpdate();
                $existing = $query->first();
                if ($existing && (!$mapping || (int) $mapping->target_id !== (int) $existing->id)) $this->fail('unmanaged_stable_code_conflict');
                if ($mapping && !$existing) $this->fail('mapped_target_missing');
                if ($mapping) {
                    foreach (['name','timezone','status'] as $field) if ($existing->$field !== $row[$field]) $this->fail('mapped_target_drift');
                    $unitCode = $type === 'student' ? $row['unit_code'] : $row['unit_codes'][0];
                    $unitId = (int) DB::table('erp_units')->where('code', $unitCode)->value('id');
                    if ($type === 'student') {
                        foreach (['registered_on','grade','syllabus'] as $field) if ($existing->$field !== $row[$field]) $this->fail('mapped_target_drift');
                        if ((int) $existing->unit_id !== $unitId) $this->fail('mapped_target_unit_drift');
                    } else {
                        $memberships = DB::table('erp_teacher_units')->where('teacher_id', $existing->id); if ($lock) $memberships->lockForUpdate();
                        $memberships = $memberships->get();
                        if ($memberships->count() !== 1 || (int) $memberships->first()->unit_id !== $unitId) $this->fail('mapped_target_unit_drift');
                    }
                }
            } elseif ($mapping) {
                $target = DB::table($this->table($type))->where('id', $mapping->target_id); if ($lock) $target->lockForUpdate();
                $target = $target->first();
                if (!$target) $this->fail('mapped_target_missing');
                if ($type !== 'eligibility' && (int) $target->unit_id !== (int) DB::table('erp_units')->where('code', $row['unit_code'])->value('id')) $this->fail('mapped_target_unit_drift');
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
        $normalize = function ($item) use (&$normalize) {
            if (!is_array($item)) return $item;
            if ($item && array_keys($item) !== range(0, count($item) - 1)) ksort($item, SORT_STRING);
            return array_map($normalize, $item);
        };
        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    private function fail(string $code): void { throw ValidationException::withMessages(['migration' => $code]); }
    private function invalid(string $code): array
    {
        return ['dry_run'=>true,'can_import'=>false,'valid'=>false,'writes'=>0,'digest'=>null,'counts'=>[],'totals'=>null,'issues'=>[['row'=>0,'field'=>'migration','code'=>$code,'blocking'=>true]]];
    }
}
