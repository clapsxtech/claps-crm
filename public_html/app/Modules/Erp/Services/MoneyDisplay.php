<?php

namespace App\Modules\Erp\Services;

/** Display persisted integer paise without converting through a floating-point value. */
class MoneyDisplay
{
    public static function inr(int $paise): string
    {
        // Divide before taking the absolute value so PHP_INT_MIN is also safe.
        $rupees = (string) abs(intdiv($paise, 100));
        $rupees = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $rupees);
        $fraction = str_pad((string) abs($paise % 100), 2, '0', STR_PAD_LEFT);

        return 'INR '.($paise < 0 ? '-' : '').$rupees.'.'.$fraction;
    }
}
