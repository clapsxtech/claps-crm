<?php
namespace App\Modules\Attendance\Services;

use App\Models\Subject;
use App\Models\WorkHour;
use App\Modules\People\Services\TeacherIdentityVerifier;
use App\Modules\Billing\Services\WorkHourAmounts;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordWorkHour
{
    public function record(Request $request): WorkHour
    {
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'],
            'subject' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'chapter' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:255'],
            'hour_taken' => ['required', 'numeric', 'gt:0', 'max:24', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'homework_status' => ['required', 'in:yes,no'],
        ]);
        // Authorize before looking up a replay. Credentials are never persisted/hashed.
        $assignment = app(TeacherIdentityVerifier::class)->verifyPair($request->all());
        $payload = [
            'teacher_name' => $assignment->teacher_name, 'teacher_code' => $assignment->teacher_code,
            'student_name' => $assignment->student_name, 'student_code' => $assignment->student_code,
            'subject_id' => (int) $data['subject'], 'date' => $data['date'],
            'chapter' => $data['chapter'], 'topic' => $data['topic'],
            'hour_taken' => number_format((float) $data['hour_taken'], 2, '.', ''),
            'homework_status' => $data['homework_status'],
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $save = function () use ($data, $payload, $hash, $assignment) {
            $existing = WorkHour::where('submission_key', $data['submission_key'])->first();
            if ($existing) return $this->replay($existing, $hash);
            $amounts = app(WorkHourAmounts::class)->calculate($assignment, $payload['hour_taken']);
            unset($payload['subject_id']);
            $subject = Subject::find($data['subject']);
            if (!$subject) throw \Illuminate\Validation\ValidationException::withMessages(['subject' => 'Choose an available subject.']);
            $payload['subject'] = $subject->name;
            return WorkHour::create($payload + $amounts + [
                'submission_key' => $data['submission_key'], 'submission_hash' => $hash,
                'date_submitted' => now(), 'dob' => null,
            ]);
        };
        try { return DB::transaction($save); }
        catch (QueryException $e) {
            // A concurrent identical request may have won the unique-key race.
            $existing = WorkHour::where('submission_key', $data['submission_key'])->first();
            if ($existing) return $this->replay($existing, $hash);
            throw $e;
        }
    }

    private function replay(WorkHour $record, string $hash): WorkHour
    {
        if (!is_string($record->submission_hash) || !hash_equals($record->submission_hash, $hash)) {
            throw new ConflictHttpException('This submission key was already used for different attendance data.');
        }
        return $record;
    }
}
