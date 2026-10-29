<?php

namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Projection only: no enrollment, attendance approval, payroll or ledger writes. */
class TransitionSheetMapper
{
    public function map(string $key, array $source, array $rows): array
    {
        $students = $this->identities('students');
        $teachers = $this->identities('teachers');
        $units = DB::table('erp_units')->whereNull('archived_at')->pluck('id')->all();
        $records = []; $occurrences = [];
        foreach ($rows as $number=>$row) {
            $data = []; $issue = null; $identity = null; $unit = null;
            foreach ($source['fields'] as $field=>$column) $data[$field] = trim((string)($row[$column] ?? ''));
            if ($key === 'timetable') {
                foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day=>$label) {
                    foreach (['time','subject','teacher','hours'] as $offset=>$field) $data[$label.'_'.$field] = trim((string)($row[3+$day*4+$offset] ?? ''));
                }
            }
            if (!array_filter($data, fn($v)=>$v !== '')) continue;
            try {
                foreach ($data as $value) {
                    if (mb_strlen($value) > 400) throw new RuntimeException('cell_too_long');
                    if (preg_match('/^#(?:REF!|N\/A|VALUE!|DIV\/0!|ERROR!|NAME\?|NUM!|SPILL!|LOADING)/i', $value)) throw new RuntimeException('formula_error');
                }
                if ($key === 'timetable') {
                    preg_match_all('/\bCCA\d+\b/i', $data['student'], $matches);
                    if (count(array_unique($matches[0])) !== 1) throw new RuntimeException('student_code_missing_or_ambiguous');
                    $data['code'] = strtoupper($matches[0][0]);
                }
                $identity = $key === 'attendance' ? $data['id'] : strtoupper($data['code']);
                $pattern = $key === 'attendance' ? '/^[1-9][0-9]{0,18}$/D' : ($key === 'teachers' ? '/^CT[0-9]{1,16}$/D' : '/^CCA[0-9]{1,16}$/D');
                if (!preg_match($pattern, $identity)) throw new RuntimeException('invalid_source_identity');
                if (in_array($key, ['students','teachers','balances'], true)) {
                    $band = strtoupper($data['band']);
                    $mapped = config('sheet_transition.band_units', [])[$band] ?? null;
                    if (!(is_int($mapped) || (is_string($mapped) && ctype_digit($mapped))) || !in_array((int)$mapped, array_map('intval', $units), true)) throw new RuntimeException('unmapped_or_archived_team');
                    $unit = (int)$mapped;
                    if ($data['name'] === '') throw new RuntimeException('name_missing');
                }
                if (in_array($key, ['students','teachers'], true)) {
                    $known = ($key === 'students' ? $students : $teachers)[$identity] ?? [];
                    $this->checkIdentity($known, $data['name'], $unit);
                    $data['code'] = $identity;
                }
                if ($key === 'attendance') {
                    $data['student_code'] = strtoupper($data['student_code']);
                    $data['teacher_code'] = strtoupper($data['teacher_code']);
                    $known = $students[$data['student_code']] ?? [];
                    if (!$known) throw new RuntimeException('unknown_student');
                    $unit = (int)$known[0]['unit_id'];
                    $this->checkIdentity($known, $data['student_name'], $unit);
                    $knownTeacher = $teachers[$data['teacher_code']] ?? [];
                    if (!$knownTeacher) throw new RuntimeException('unknown_teacher');
                    // Cross-team teaching is allowed; student ownership determines visibility.
                    $this->checkIdentity($knownTeacher, $data['teacher_name'], null);
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['date']);
                    if (!$date || $date->format('Y-m-d') !== $data['date']) throw new RuntimeException('invalid_date');
                    if (!preg_match('/^\d{1,2}(?:\.\d{1,4})?$/D', $data['hours']) || (float)$data['hours'] <= 0 || (float)$data['hours'] > 24) throw new RuntimeException('invalid_hours');
                }
                if (in_array($key, ['balances','timetable'], true)) {
                    $known = $students[$identity] ?? [];
                    if (!$known) throw new RuntimeException('unknown_student');
                    if ($key === 'timetable') $unit = (int)$known[0]['unit_id'];
                    $this->checkIdentity($known, $key === 'balances' ? $data['name'] : null, $unit);
                    if ($key === 'balances') {
                        // Preserve reported precision, never coerce an empty/error balance to zero.
                        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,8})?$/D', $data['balance'])) throw new RuntimeException('invalid_balance');
                        $data['code'] = $identity;
                    }
                }
            } catch (RuntimeException $e) { $issue = $e->getMessage(); }
            // Store only the allowlisted fields; oversized cells are clipped in the review queue.
            $data = array_map(fn($v)=>mb_substr($v,0,400), $data);
            if (strlen(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > 16000) {
                $issue = 'record_too_large';
                $data = array_map(fn($v)=>mb_substr($v,0,100), $data);
            }
            $records[] = ['unit_id'=>$unit,'source_row'=>(int)$number,'source_identity'=>$identity ? mb_substr($identity,0,100) : null,'issue'=>$issue,'payload'=>json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
            if ($identity !== null) $occurrences[$identity][] = count($records)-1;
        }
        foreach ($occurrences as $indices) if (count($indices) > 1) {
            foreach ($indices as $index) $records[$index]['issue'] = 'duplicate_source_identity';
        }
        return $records;
    }

    private function checkIdentity(array $known, ?string $name, ?int $unit): void
    {
        foreach ($known as $entry) {
            if ($name !== null && $this->name($entry['name']) !== $this->name($name)) throw new RuntimeException('identity_name_conflict');
            if ($unit !== null && $entry['unit_id'] !== null && (int)$entry['unit_id'] !== $unit) throw new RuntimeException('identity_team_conflict');
        }
    }

    private function name(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u',' ',trim($name)));
    }

    private function identities(string $kind): array
    {
        $result = [];
        $query = DB::table('erp_'.$kind)->select(['code','name']);
        if ($kind === 'students') $query->addSelect('unit_id');
        foreach ($query->get() as $row) $result[strtoupper($row->code)][] = ['name'=>$row->name,'unit_id'=>$row->unit_id ?? null];
        $active = DB::table('erp_sheet_sources')->where('source_key',$kind)->value('active_run_id');
        if ($active) {
            foreach (DB::table('erp_sheet_records')->where('run_id',$active)->whereNull('issue')->get() as $row) {
                $data = json_decode($row->payload, true, 64, JSON_THROW_ON_ERROR);
                $result[$row->source_identity][] = ['name'=>$data['name'],'unit_id'=>$row->unit_id];
            }
        }
        return $result;
    }
}
