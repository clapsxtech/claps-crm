<?php

namespace Tests\Unit;

use App\Modules\Erp\Services\MoneyDisplay;
use App\Modules\Erp\Services\MoneyInput;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ErpMoneyInputTest extends TestCase
{
    public function test_exact_decimal_conversion_and_limits_without_float_rounding(): void
    {
        foreach (['0' => 0, '0.00' => 0, '0.01' => 1, '0.29' => 29, '1' => 100, '1.2' => 120, '1234.56' => 123456, '10000000.00' => 1000000000] as $value => $paise) {
            $this->assertSame($paise, MoneyInput::toPaise((string) $value, 'amount_inr', 1000000000, true));
        }
        $this->assertSame(1200, MoneyInput::toPaise(12, 'amount_inr', 1000000000));
        $this->assertSame(['amount_paise' => 1], MoneyInput::forAction(['amount_inr' => '0.01'], 'payment'));
        $this->assertSame(['student_rate_paise' => 0, 'teacher_rate_paise' => 100000000], MoneyInput::forAction(['student_rate_inr' => '0.00', 'teacher_rate_inr' => '1000000.00'], 'assign'));
    }

    public function test_invalid_decimal_inputs_are_rejected(): void
    {
        foreach (['', ' 1', '1 ', "1\n", '01', '00.01', '.50', '1.', '1.001', '1.000', '1e2', '1E+2', '+1', '-1', '-0', '1,000', '1,25', 'NaN', 'INF', '₹100', '１００', true, false, null, [], ['1'], new \stdClass(), 1.0, 0.01] as $value) {
            $this->assertInvalid($value, 1000000000, true);
        }
        foreach (['0', '0.0', '0.00', 0] as $value) {
            $this->assertInvalid($value, 1000000000);
        }
        foreach (['10000000.01', '999999999999999999999999999999.99', PHP_INT_MAX] as $value) {
            $this->assertInvalid($value, 1000000000);
        }
        $this->assertInvalid('1000000.01', 100000000, true);
    }

    public function test_paise_api_is_unchanged_and_normalization_is_limited_to_known_actions(): void
    {
        foreach (['payment', 'request-create', 'request-allocate', 'receipt-correct', 'salary-paid'] as $action) {
            $payload = ['amount_paise' => '12345', 'reference' => 'synthetic'];
            $this->assertSame($payload, MoneyInput::forAction($payload, $action));
            $this->assertSame(['reference' => 'synthetic', 'amount_paise' => 12345], MoneyInput::forAction(['amount_inr' => '123.45', 'reference' => 'synthetic'], $action));
        }
        $rates = ['student_rate_paise' => 0, 'teacher_rate_paise' => '6000'];
        $this->assertSame($rates, MoneyInput::forAction($rates, 'assign'));
        $payload = ['amount_inr' => 'not money', 'amount_paise' => 0];
        $this->assertSame($payload, MoneyInput::forAction($payload, 'student'));
    }

    public function test_mixed_units_are_rejected_even_when_paise_is_null_or_names_differ(): void
    {
        foreach ([
            ['payment', ['amount_inr' => '1', 'amount_paise' => 100]],
            ['payment', ['amount_inr' => '1', 'amount_paise' => null]],
            ['payment', ['amount_inr' => null, 'amount_paise' => 100]],
            ['assign', ['student_rate_inr' => '0', 'teacher_rate_paise' => 5000]],
            ['assign', ['student_rate_paise' => 0, 'teacher_rate_inr' => '50']],
            ['assign', ['student_rate_inr' => '0', 'student_rate_paise' => 0, 'teacher_rate_inr' => '50']],
        ] as [$action, $payload]) {
            try {
                MoneyInput::forAction($payload, $action);
                $this->fail('Mixed units were accepted.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('not both', implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
    }

    public function test_signed_money_display_has_two_decimals_and_handles_integer_extremes(): void
    {
        foreach ([0 => 'INR 0.00', 1 => 'INR 0.01', -1 => 'INR -0.01', 29 => 'INR 0.29', 100 => 'INR 1.00', -123456 => 'INR -1,234.56', 1000000000 => 'INR 10,000,000.00'] as $paise => $formatted) {
            $this->assertSame($formatted, MoneyDisplay::inr($paise));
        }
        if (PHP_INT_SIZE === 8) {
            $this->assertSame('INR 92,233,720,368,547,758.07', MoneyDisplay::inr(PHP_INT_MAX));
            $this->assertSame('INR -92,233,720,368,547,758.08', MoneyDisplay::inr(PHP_INT_MIN));
        }
    }

    private function assertInvalid($value, int $limit, bool $allowZero = false): void
    {
        try {
            MoneyInput::toPaise($value, 'test_inr', $limit, $allowZero);
            $this->fail('Invalid INR input was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('test_inr', $e->errors());
        }
    }
}
