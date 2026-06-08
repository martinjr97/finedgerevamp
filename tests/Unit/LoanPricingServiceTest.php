<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(LoanPricingService::class);
    }

    public function test_upfront_flat_term_rate_interest_is_principal_times_term_percentage(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
        );

        $quote = $this->pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => 1,
            'start_date' => '2026-01-01',
            'term_days' => 30,
            'loan_rate' => $context['loan_rate'],
            'loan_rate_type' => $context['rate_type'],
        ]);

        $this->assertSame('2780.00', $quote['interest']);
        $this->assertSame('12780.00', $quote['total_amount']);
        $this->assertSame('upfront_flat', $quote['interest_behavior']);
        $this->assertSame('term_percentage', $quote['rate_input_mode']);
        $this->assertSame('27.8000', $quote['quoted_term_rate']);
        $this->assertNull($quote['derived_daily_rate']);
    }

    public function test_daily_accrual_term_rate_projects_full_term_interest_and_derived_daily_rate(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
        );

        $termDays = 30;
        $derived = $this->pricing->calculateDerivedDailyRateFromTerm(27.8, $termDays);

        $this->assertSame('0.00926667', $derived);

        $quote = $this->pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => 1,
            'start_date' => '2026-01-01',
            'term_days' => $termDays,
            'loan_rate' => $context['loan_rate'],
            'loan_rate_type' => $context['rate_type'],
        ]);

        $this->assertSame('2780.00', $quote['interest']);
        $this->assertSame('daily_accrual', $quote['interest_behavior']);
        $this->assertSame('0.00926667', $quote['derived_daily_rate']);
    }

    public function test_legacy_daily_multiplier_interest(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            rateInputMode: LoanRateType::RATE_INPUT_DAILY_MULTIPLIER,
            dailyRate: 0.00926667,
        );

        $interest = $this->pricing->calculateInterest([
            'principal' => 10000,
            'term_days' => 30,
            'loan_rate' => $context['loan_rate'],
            'loan_rate_type' => $context['rate_type'],
            'daily_rate' => '0.00926667',
        ]);

        $this->assertSame('2780.00', $interest);
    }

    public function test_legacy_weekly_multiplier_interest(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            rateInputMode: LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
            accrualPeriod: LoanRateType::ACCRUAL_PERIOD_WEEKLY,
            weeklyRate: 0.05,
        );

        $interest = $this->pricing->calculateInterest([
            'principal' => 10000,
            'term_days' => 30,
            'loan_rate' => $context['loan_rate'],
            'loan_rate_type' => $context['rate_type'],
        ]);

        $this->assertSame('2500.00', $interest);
    }

    public function test_processing_fee_calculation(): void
    {
        $fee = $this->pricing->calculateProcessingFee(10000, 10);

        $this->assertSame('1000.00', $fee);
    }

    public function test_installment_rounding_last_period_absorbs_remainder(): void
    {
        $result = $this->pricing->calculateInstallments('12780.05', 3);

        $this->assertSame('4260.02', $result['installment_amount']);
        $this->assertCount(3, $result['installments']);
        $this->assertSame('4260.02', $result['installments'][0]['amount']);
        $this->assertSame('4260.02', $result['installments'][1]['amount']);
        $this->assertSame('4260.01', $result['installments'][2]['amount']);

        $sum = array_reduce(
            $result['installments'],
            fn (float $carry, array $row) => $carry + (float) $row['amount'],
            0.0
        );

        $this->assertEqualsWithDelta(12780.05, $sum, 0.001);
    }

    public function test_installments_sum_exactly_when_total_divides_evenly(): void
    {
        $result = $this->pricing->calculateInstallments('12780.00', 3);

        $sum = array_sum(array_map(fn (array $row) => (float) $row['amount'], $result['installments']));
        $this->assertSame(12780.00, $sum);
        $this->assertSame('4260.00', $result['installments'][2]['amount']);
    }

    public function test_resolve_rate_for_amount_matches_principal_within_band(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 18,
            tenureMonths: 3,
            minPrincipal: 5000,
            maxPrincipal: 15000,
        );

        $resolved = $this->pricing->resolveRateForAmount($context['rate_type'], 3, 11000);

        $this->assertNotNull($resolved);
        $this->assertSame($context['loan_rate']->id, $resolved->id);
        $this->assertEquals(18.0, (float) $resolved->term_interest_percentage);
    }

    public function test_resolve_rate_returns_null_when_principal_outside_band_and_no_open_ended_row(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 18,
            tenureMonths: 3,
            minPrincipal: 5000,
            maxPrincipal: 15000,
        );

        $resolved = $this->pricing->resolveRateForAmount($context['rate_type'], 3, 1000);

        $this->assertNull($resolved);
    }

    public function test_resolve_rate_falls_back_to_open_ended_tenure_row(): void
    {
        $context = $this->makeRateContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 12,
            minPrincipal: null,
            maxPrincipal: null,
        );

        $resolved = $this->pricing->resolveRateForAmount($context['rate_type'], 1, 500);

        $this->assertNotNull($resolved);
        $this->assertEquals(12.0, (float) $resolved->term_interest_percentage);
    }

    public function test_calculate_term_days_uses_calendar_months(): void
    {
        $days = $this->pricing->calculateTermDays('2026-01-15', 1);

        $expected = Carbon::parse('2026-01-15')->diffInDays(Carbon::parse('2026-01-15')->addMonths(1));
        $this->assertSame((int) $expected, $days);
    }

    public function test_component_installments_allow_zero_fee_or_interest_for_single_month(): void
    {
        $result = $this->pricing->calculateComponentInstallments(10000, 0, 1200, 1);

        $this->assertCount(1, $result['installments']);
        $row = $result['installments'][0];
        $this->assertSame(1, $row['period']);
        $this->assertEqualsWithDelta(0.0, (float) $row['fee_component'], 0.01);
        $this->assertEqualsWithDelta(1200.0, (float) $row['interest_component'], 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $row['principal_component'], 0.01);
        $this->assertEqualsWithDelta(11200.0, (float) $row['expected_amount'], 0.01);
    }

    public function test_component_installments_sum_to_principal_fee_and_interest(): void
    {
        $result = $this->pricing->calculateComponentInstallments(10000, 500, 2780, 3);

        $principalSum = 0;
        $feeSum = 0;
        $interestSum = 0;
        $totalSum = 0;

        foreach ($result['installments'] as $row) {
            $principalSum += (float) $row['principal_component'];
            $feeSum += (float) $row['fee_component'];
            $interestSum += (float) $row['interest_component'];
            $totalSum += (float) $row['expected_amount'];

            $this->assertEqualsWithDelta(
                (float) $row['expected_amount'],
                (float) $row['principal_component'] + (float) $row['fee_component'] + (float) $row['interest_component'],
                0.01
            );
        }

        $this->assertEqualsWithDelta(10000.0, $principalSum, 0.01);
        $this->assertEqualsWithDelta(500.0, $feeSum, 0.01);
        $this->assertEqualsWithDelta(2780.0, $interestSum, 0.01);
        $this->assertEqualsWithDelta(13280.0, $totalSum, 0.01);
    }

    /**
     * @return array{rate_type: LoanRateType, loan_rate: LoanRate}
     */
    private function makeRateContext(
        string $interestBehavior,
        string $rateInputMode,
        ?float $termInterestPercentage = null,
        ?float $dailyRate = null,
        ?float $weeklyRate = null,
        string $accrualPeriod = LoanRateType::ACCRUAL_PERIOD_DAILY,
        ?float $minPrincipal = null,
        ?float $maxPrincipal = null,
        int $tenureMonths = 1,
        bool $createDefaultRate = true,
    ): array {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Pricing Co '.$suffix,
            'slug' => 'pricing-co-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Pricing Product',
            'code' => 'PP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate Type '.$suffix,
            'code' => 'RT-'.$suffix,
            'accrual_period' => $accrualPeriod,
            'interest_behavior' => $interestBehavior,
            'rate_input_mode' => $rateInputMode,
            'is_active' => true,
        ]);

        $loanRate = null;

        if ($createDefaultRate) {
            $loanRate = LoanRate::create([
                'loan_rate_type_id' => $rateType->id,
                'tenure_months' => $tenureMonths,
                'processing_fee_percentage' => 0,
                'term_interest_percentage' => $termInterestPercentage,
                'min_principal' => $minPrincipal,
                'max_principal' => $maxPrincipal,
                'daily_rate' => $dailyRate,
                'weekly_rate' => $weeklyRate,
                'arrear_rate' => 0.01,
                'is_active' => true,
            ]);
        }

        return [
            'rate_type' => $rateType,
            'loan_rate' => $loanRate,
        ];
    }
}
