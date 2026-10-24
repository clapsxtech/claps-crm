<?php

namespace App\Modules\Erp\Services;

use Illuminate\Validation\ValidationException;

/** HTTP presentation adapter only. Domain services continue to receive integer paise. */
class MoneyInput
{
    public static function forAction(array $payload, string $action): array
    {
        if ($action === 'assign') {
            $fields = ['student_rate_inr' => 'student_rate_paise', 'teacher_rate_inr' => 'teacher_rate_paise'];
            $maxPaise = 100000000;
            $allowZero = true;
        } elseif (in_array($action, ['payment', 'request-create', 'request-allocate', 'receipt-correct', 'salary-paid'], true)) {
            $fields = ['amount_inr' => 'amount_paise'];
            $maxPaise = 1000000000;
            $allowZero = false;
        } else {
            return $payload;
        }

        $inrFields = array_intersect_key($payload, $fields);
        if (!$inrFields) {
            // Existing API clients and integrations still supply paise; leave them unchanged.
            return $payload;
        }
        foreach ($fields as $inr => $paise) {
            if (array_key_exists($paise, $payload)) {
                throw ValidationException::withMessages([
                    array_key_first($inrFields) => 'Use INR fields or paise fields, not both in the same request.',
                ]);
            }
        }
        foreach ($inrFields as $field => $value) {
            $payload[$fields[$field]] = self::toPaise($value, $field, $maxPaise, $allowZero);
            unset($payload[$field]);
        }

        return $payload;
    }

    public static function toPaise($value, string $field, int $maxPaise, bool $allowZero = false): int
    {
        // JSON integers are exact; JSON floats are not accepted. HTML forms submit strings.
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/', (string) $value)) {
            throw ValidationException::withMessages([
                $field => 'Enter an INR amount such as 100 or 100.50, with at most two decimal places and no commas or signs.',
            ]);
        }
        $parts = explode('.', (string) $value, 2);
        $digits = ltrim($parts[0].str_pad($parts[1] ?? '', 2, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = (string) $maxPaise;
        // Compare decimal digit strings before casting or multiplying, including huge inputs.
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw ValidationException::withMessages([$field => 'The maximum supported amount is '.MoneyDisplay::inr($maxPaise).'.']);
        }
        if (!$allowZero && $digits === '0') {
            throw ValidationException::withMessages([$field => 'Enter an amount greater than INR 0.00.']);
        }

        return (int) $digits;
    }
}
