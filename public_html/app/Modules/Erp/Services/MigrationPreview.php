<?php

namespace App\Modules\Erp\Services;

/** Pure validation of a normalized migration batch. Never persists or replays events. */
class MigrationPreview
{
    private const TYPES = ['student', 'teacher', 'assignment', 'opening_balance', 'historical_attendance'];

    public function preview(array $rows, array $references = []): array
    {
        $issues = [];
        $counts = array_fill_keys(self::TYPES, 0);
        $index = [];
        $duplicates = [];
        $totals = ['student_balance_paise' => 0, 'teacher_payable_paise' => 0, 'historical_financial_effect_paise' => 0];
        $issue = function ($row, $field, $code) use (&$issues) {
            $issues[] = ['row' => $row, 'field' => $field, 'code' => $code, 'blocking' => true];
        };
        if (!$this->date($references['cutoff_date'] ?? null)) $issue(0, 'cutoff_date', 'required_valid_cutoff');
        $manifest = $references['source_manifest'] ?? null;
        if (!is_array($manifest) || !$this->identifier($manifest['source_id'] ?? null) || !$this->instant($manifest['exported_at'] ?? null)) $issue(0, 'source_manifest', 'required_source_manifest');
        if (!$rows) $issue(0, 'rows', 'empty_batch');
        foreach (array_values($rows) as $i => $row) {
            $n = $i + 1;
            if (!is_array($row) || !in_array($row['type'] ?? null, self::TYPES, true)) {
                $issue($n, 'type', 'unknown_row_type');
                continue;
            }
            $type = $row['type'];
            ++$counts[$type];
            if (!$this->identifier($row['external_id'] ?? null)) {
                $issue($n, 'external_id', 'invalid_stable_id');
                continue;
            }
            $id = $row['external_id'];
            if (isset($index[$type][$id])) {
                $issue($n, 'external_id', 'duplicate_stable_id');
                $duplicates[$type][$id] = true;
            } else {
                $index[$type][$id] = $row;
            }
        }
        $seen = [];
        foreach (array_values($rows) as $i => $row) {
            $n = $i + 1;
            if (!is_array($row) || !in_array($row['type'] ?? null, self::TYPES, true)) continue;
            $type = $row['type'];
            $ref = function ($field, $target) use ($row, $n, $index, $duplicates, $issue) {
                $id = $row[$field] ?? null;
                if (!$this->identifier($id) || !isset($index[$target][$id])) {
                    $issue($n, $field, 'missing_reference');
                    return null;
                }
                if (isset($duplicates[$target][$id])) $issue($n, $field, 'ambiguous_reference');
                return $index[$target][$id];
            };
            if ($type === 'teacher') {
                $units = $row['unit_codes'] ?? null;
                if (!is_array($units) || count($units) !== 1) {
                    $issue($n, 'unit_codes', 'invalid_unit_list');
                } else {
                    foreach ($units as $unit) {
                        if (!$this->identifier($unit) || !in_array($unit, $references['unit_codes'] ?? [], true)) $issue($n, 'unit_codes', 'unknown_unit');
                    }
                }
            } else {
                if (!$this->identifier($row['unit_code'] ?? null) || !in_array($row['unit_code'], $references['unit_codes'] ?? [], true)) $issue($n, 'unit_code', 'unknown_unit');
            }
            if (in_array($type, ['student', 'teacher'], true)) {
                if (!is_string($row['name'] ?? null) || trim($row['name']) === '') $issue($n, 'name', 'required_name');
                if (!is_string($row['timezone'] ?? null) || !in_array($row['timezone'], \DateTimeZone::listIdentifiers(), true)) $issue($n, 'timezone', 'invalid_timezone');
            }
            if ($type === 'student') {
                if (!$this->date($row['registered_on'] ?? null)) $issue($n, 'registered_on', 'invalid_date');
                foreach (['grade', 'syllabus'] as $field) if (!is_string($row[$field] ?? null) || trim($row[$field]) === '') $issue($n, $field, 'required_value');
            }
            if ($type === 'assignment') {
                $student = $ref('student_id', 'student');
                $teacher = $ref('teacher_id', 'teacher');
                if ($student && ($student['unit_code'] ?? null) !== ($row['unit_code'] ?? null)) $issue($n, 'student_id', 'cross_unit_reference');
                if ($teacher && !in_array($row['unit_code'] ?? null, is_array($teacher['unit_codes'] ?? null) ? $teacher['unit_codes'] : [], true)) $issue($n, 'teacher_id', 'cross_unit_reference');
                if (!$this->identifier($row['subject_code'] ?? null) || !in_array($row['subject_code'], $references['subject_codes'] ?? [], true)) $issue($n, 'subject_code', 'unknown_subject');
                foreach (['student_rate_paise', 'teacher_rate_paise'] as $field) if (!$this->money($row[$field] ?? null) || $row[$field] < 0 || $row[$field] > 4294967295) $issue($n, $field, 'invalid_integer_paise');
                if (($row['currency'] ?? null) !== 'INR') $issue($n, 'currency', 'unsupported_currency');
                if (!$this->date($row['starts_on'] ?? null)) $issue($n, 'starts_on', 'invalid_date');
                if (isset($row['ends_on']) && (!$this->date($row['ends_on']) || $row['ends_on'] < ($row['starts_on'] ?? ''))) $issue($n, 'ends_on', 'invalid_date_range');
                $key = json_encode([$row['student_id'] ?? null, $row['teacher_id'] ?? null, $row['subject_code'] ?? null, $row['starts_on'] ?? null]);
                if (isset($seen[$type][$key])) $issue($n, 'external_id', 'duplicate_assignment');
                $seen[$type][$key] = true;
                foreach (array_slice(array_values($rows), 0, $i) as $earlier) {
                    if (!is_array($earlier) || ($earlier['type'] ?? null) !== 'assignment') continue;
                    if (($earlier['student_id'] ?? null) === ($row['student_id'] ?? null) && ($earlier['subject_code'] ?? null) === ($row['subject_code'] ?? null)
                        && $this->date($earlier['starts_on'] ?? null) && $this->date($row['starts_on'] ?? null)
                        && ($earlier['starts_on'] <= ($row['ends_on'] ?? '9999-12-31')) && ($row['starts_on'] <= ($earlier['ends_on'] ?? '9999-12-31'))) {
                        $issue($n, 'starts_on', 'overlapping_student_subject_assignment');
                    }
                }
            }
            if ($type === 'opening_balance') {
                $owner = $row['owner_type'] ?? null;
                if (!in_array($owner, ['student', 'teacher'], true)) {
                    $issue($n, 'owner_type', 'invalid_owner_type');
                } else {
                    $person = $ref('owner_id', $owner);
                    if ($person && (($owner === 'student' && ($person['unit_code'] ?? null) !== ($row['unit_code'] ?? null)) || ($owner === 'teacher' && !in_array($row['unit_code'] ?? null, is_array($person['unit_codes'] ?? null) ? $person['unit_codes'] : [], true)))) $issue($n, 'owner_id', 'cross_unit_reference');
                    $key = json_encode([$row['unit_code'] ?? null, $owner, $row['owner_id'] ?? null]);
                    if (isset($seen[$type][$key])) $issue($n, 'owner_id', 'duplicate_opening_balance');
                    $seen[$type][$key] = true;
                    if ($this->money($row['amount_paise'] ?? null)) {
                        $field = $owner === 'student' ? 'student_balance_paise' : 'teacher_payable_paise';
                        $sum = $totals[$field] + $row['amount_paise'];
                        if (!is_int($sum)) $issue($n, 'amount_paise', 'total_overflow');
                        else $totals[$field] = $sum;
                    }
                }
                if ($owner === 'teacher' && $this->money($row['amount_paise'] ?? null)) {
                    if ($row['amount_paise'] < 0) $issue($n, 'amount_paise', 'teacher_advance_requires_review');
                    if ($row['amount_paise'] > 1000000000) $issue($n, 'amount_paise', 'teacher_opening_exceeds_operational_limit');
                }
                if (!$this->money($row['amount_paise'] ?? null)) $issue($n, 'amount_paise', 'invalid_integer_paise');
                if (($row['currency'] ?? null) !== 'INR') $issue($n, 'currency', 'unsupported_currency');
                if (!$this->date($row['as_of'] ?? null)) $issue($n, 'as_of', 'invalid_date');
                if (($row['as_of'] ?? null) !== ($references['cutoff_date'] ?? null)) $issue($n, 'as_of', 'inconsistent_cutoff');
            }
            if ($type === 'historical_attendance') {
                $assignment = $ref('assignment_id', 'assignment');
                if ($assignment && ($assignment['unit_code'] ?? null) !== ($row['unit_code'] ?? null)) $issue($n, 'assignment_id', 'cross_unit_reference');
                if (!$this->instant($row['starts_at'] ?? null)) $issue($n, 'starts_at', 'invalid_utc_timestamp');
                else {
                    $day = substr($row['starts_at'], 0, 10);
                    if ($assignment && ($day < ($assignment['starts_on'] ?? '') || $day > ($assignment['ends_on'] ?? '9999-12-31'))) $issue($n, 'starts_at', 'outside_assignment_dates');
                    if ($this->date($references['cutoff_date'] ?? null) && $day >= $references['cutoff_date']) $issue($n, 'starts_at', 'history_not_before_cutoff');
                }
                if (!is_int($row['actual_minutes'] ?? null) || $row['actual_minutes'] < 0 || $row['actual_minutes'] > 1440) $issue($n, 'actual_minutes', 'invalid_minutes');
                if (!in_array($row['status'] ?? null, ['delivered', 'cancelled', 'absent'], true)) $issue($n, 'status', 'invalid_historical_status');
                if (($row['financial_effect'] ?? null) !== 'none') $issue($n, 'financial_effect', 'historical_replay_forbidden');
                foreach (['amount_paise', 'student_charge_paise', 'teacher_pay_paise', 'ledger', 'approve', 'post_financials'] as $field) if (array_key_exists($field, $row)) $issue($n, $field, 'historical_replay_forbidden');
                $key = json_encode([$row['assignment_id'] ?? null, $row['starts_at'] ?? null]);
                if (isset($seen[$type][$key])) $issue($n, 'external_id', 'duplicate_historical_session');
                $seen[$type][$key] = true;
            }
        }
        return ['dry_run' => true, 'can_import' => false, 'valid' => !$issues, 'counts' => $counts, 'totals' => $issues ? null : $totals, 'issues' => $issues, 'writes' => 0];
    }

    private function identifier($value): bool
    {
        return is_string($value) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $value) === 1;
    }

    private function money($value): bool { return is_int($value); }

    private function date($value): bool
    {
        if (!is_string($value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function instant($value): bool
    {
        if (!is_string($value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }
}
