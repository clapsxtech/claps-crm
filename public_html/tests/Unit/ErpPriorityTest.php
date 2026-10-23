<?php
namespace Tests\Unit;
use App\Modules\Erp\Services\People;
use PHPUnit\Framework\TestCase;
class ErpPriorityTest extends TestCase
{
    public function test_registration_calendar_month_not_elapsed_days_or_performance(): void
    {
        $p=new People();
        $this->assertSame('Super Priority',$p->priority('2026-01-01','Asia/Kolkata','2026-01-31'));
        $this->assertSame('High Priority',$p->priority('2025-12-31','Asia/Kolkata','2026-01-01'));
        $this->assertSame('Medium Priority',$p->priority('2025-11-30','Asia/Kolkata','2026-01-01'));
        $this->assertSame('Medium Priority',$p->priority('2023-01-01','Asia/Kolkata','2026-01-01'));
        $this->assertSame('Future registration',$p->priority('2026-02-01','Asia/Kolkata','2026-01-31'));
    }
}
