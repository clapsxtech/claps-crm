<?php
namespace App\Modules\Billing\Services;

use App\Models\Master;
use Illuminate\Validation\ValidationException;

/** Existing billing rule: total amount = hours taken × master.hour_payment. */
class WorkHourAmounts
{
    public function calculate(Master $assignment, string $hours): array
    {
        $amounts = [];
        foreach (['hour_payment', 'payment_per_hour', 'profit_per_hour'] as $column) {
            $rate = (string) $assignment->getAttribute($column);
            $pattern = $column === 'profit_per_hour' ? '/^-?\d{1,8}(?:\.\d{1,2})?$/D' : '/^\d{1,8}(?:\.\d{1,2})?$/D';
            if (!preg_match($pattern, $rate)) {
                throw ValidationException::withMessages(['hour_taken' => 'The assigned rates need administrator review before this record can be submitted.']);
            }
            $amounts[$column] = number_format((float) $rate, 2, '.', '');
        }
        // Decimal arithmetic avoids floating-point drift in persisted financial values.
        $amounts['total_amount'] = bcdiv(bcadd(bcmul($hours, $amounts['hour_payment'], 4), '0.005', 4), '1', 2);
        return $amounts;
    }
}
