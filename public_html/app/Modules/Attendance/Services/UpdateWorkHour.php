<?php
namespace App\Modules\Attendance\Services;

use App\Models\Master;
use App\Models\WorkHour;
use App\Modules\Billing\Services\WorkHourAmounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Administrative corrections preserve the historical identity and rate snapshot. */
class UpdateWorkHour
{
    public function update(int $id, Request $request): WorkHour
    {
        $data = $request->validate([
            'teacher_name' => ['required', 'string'], 'teacher_code' => ['required', 'string'],
            'student_names' => ['required', 'string'], 'student_code' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'chapter' => ['required', 'string', 'max:255'], 'topic' => ['required', 'string', 'max:255'],
            'hour_taken' => ['required', 'numeric', 'gt:0', 'max:24', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'homework_status' => ['required', 'in:yes,no'],
        ]);
        return DB::transaction(function () use ($id, $data) {
            $record = WorkHour::lockForUpdate()->findOrFail($id);
            foreach (['teacher_name' => 'teacher_name', 'teacher_code' => 'teacher_code', 'student_names' => 'student_name', 'student_code' => 'student_code'] as $input => $column) {
                if ($data[$input] !== $record->$column) {
                    throw ValidationException::withMessages([$input => 'The original attendance identity cannot be reassigned.']);
                }
            }
            $hours = number_format((float) $data['hour_taken'], 2, '.', '');
            $snapshot = new Master($record->only(['hour_payment', 'payment_per_hour', 'profit_per_hour']));
            $amounts = app(WorkHourAmounts::class)->calculate($snapshot, $hours);
            $record->fill(array_intersect_key($data, array_flip(['subject', 'date', 'chapter', 'topic', 'homework_status'])) + ['hour_taken' => $hours] + $amounts);
            // date_submitted, credentials, rates and submission identity are not editable inputs.
            $record->save();
            return $record;
        });
    }
}
