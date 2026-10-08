<?php

namespace Tests\Unit;

use App\Support\ArrearRate;
use Tests\TestCase;

class ArrearRateTest extends TestCase
{
    public function test_from_percentage_stores_daily_factor(): void
    {
        $this->assertSame('0.01000', ArrearRate::fromPercentage(1));
        $this->assertSame('0.02500', ArrearRate::fromPercentage(2.5));
    }

    public function test_to_percentage_displays_admin_value(): void
    {
        $this->assertSame('1.00', ArrearRate::toPercentage('0.01'));
        $this->assertSame('2.50', ArrearRate::toPercentage('0.025'));
    }

    public function test_accrual_charge_uses_stored_factor(): void
    {
        $charge = ArrearRate::calculateDailyCharge('1000', ArrearRate::fromPercentage(2.5));
        $this->assertSame('25.00', $charge);
    }
}
