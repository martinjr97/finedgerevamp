<?php

namespace Tests\Unit;

use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanRateRowService;
use Tests\TestCase;

class LoanRateRowPreviewTest extends TestCase
{
    public function test_preview_approximate_rates_from_term_percentage(): void
    {
        $service = app(LoanRateRowService::class);

        $daily = $service->previewApproximateDailyRateFromTerm(27.8, 1);
        $weekly = $service->previewApproximateWeeklyRateFromTerm(27.8, 1);

        $this->assertEqualsWithDelta(0.00926667, (float) $daily, 0.0000001);
        $this->assertEqualsWithDelta(0.06950000, (float) $weekly, 0.0000001);
    }

    public function test_format_display_rates_for_term_percentage_row(): void
    {
        $service = app(LoanRateRowService::class);

        $rateType = new LoanRateType([
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
        ]);

        $rate = new LoanRate([
            'tenure_months' => 1,
            'term_interest_percentage' => 27.8,
            'derived_daily_rate' => 0.00926667,
        ]);

        $daily = $service->formatDisplayDailyRate($rate, $rateType);
        $weekly = $service->formatDisplayWeeklyRate($rate, $rateType);

        $this->assertNotNull($daily);
        $this->assertTrue($daily['approximate']);
        $this->assertSame('0.00927', $daily['value']);

        $this->assertNotNull($weekly);
        $this->assertTrue($weekly['approximate']);
        $this->assertSame('0.06950', $weekly['value']);
    }

    public function test_format_display_rates_use_explicit_multipliers_when_set(): void
    {
        $service = app(LoanRateRowService::class);

        $rateType = new LoanRateType([
            'rate_input_mode' => LoanRateType::RATE_INPUT_DAILY_MULTIPLIER,
        ]);

        $rate = new LoanRate([
            'tenure_months' => 1,
            'daily_rate' => 0.03,
            'weekly_rate' => 0.05,
        ]);

        $daily = $service->formatDisplayDailyRate($rate, $rateType);
        $weekly = $service->formatDisplayWeeklyRate($rate, $rateType);

        $this->assertFalse($daily['approximate']);
        $this->assertSame('0.03000', $daily['value']);
        $this->assertFalse($weekly['approximate']);
        $this->assertSame('0.05000', $weekly['value']);
    }
}
