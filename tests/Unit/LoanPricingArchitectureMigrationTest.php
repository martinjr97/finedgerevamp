<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanPricingArchitectureMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_rate_types_has_pricing_behavior_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('loan_rate_types', 'interest_behavior'));
        $this->assertTrue(Schema::hasColumn('loan_rate_types', 'rate_input_mode'));
    }

    public function test_loan_rates_has_term_and_band_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('loan_rates', 'term_interest_percentage'));
        $this->assertTrue(Schema::hasColumn('loan_rates', 'min_principal'));
        $this->assertTrue(Schema::hasColumn('loan_rates', 'max_principal'));
        $this->assertTrue(Schema::hasColumn('loan_rates', 'derived_daily_rate'));
    }

    public function test_loans_has_settlement_snapshot_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('loans', 'quoted_term_rate'));
        $this->assertTrue(Schema::hasColumn('loans', 'interest_behavior'));
        $this->assertTrue(Schema::hasColumn('loans', 'settlement_amount'));
        $this->assertTrue(Schema::hasColumn('loans', 'settlement_date'));
        $this->assertTrue(Schema::hasColumn('loans', 'rebate_amount'));
    }

    public function test_legacy_columns_remain_on_all_tables(): void
    {
        $this->assertTrue(Schema::hasColumn('loan_rate_types', 'accrual_period'));
        $this->assertTrue(Schema::hasColumn('loan_rates', 'daily_rate'));
        $this->assertTrue(Schema::hasColumn('loan_rates', 'weekly_rate'));
        $this->assertTrue(Schema::hasColumn('loans', 'daily_rate'));
        $this->assertTrue(Schema::hasColumn('loans', 'accrual_type'));
        $this->assertTrue(Schema::hasColumn('loans', 'loan_settled_date'));
    }

    public function test_loan_rate_type_models_accept_new_attributes(): void
    {
        $company = $this->makeCompany();
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Test Product',
            'code' => 'TST-'.Str::lower(Str::random(6)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Term Rate Plan',
            'code' => 'TRP-'.Str::lower(Str::random(6)),
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $this->assertSame('upfront_flat', $rateType->interest_behavior);
        $this->assertSame('term_percentage', $rateType->rate_input_mode);
        $this->assertTrue($rateType->booksInterestUpfront());
        $this->assertFalse($rateType->usesLegacyMultiplierInput());
    }

    public function test_loan_rate_row_supports_term_percentage_and_amount_band(): void
    {
        $company = $this->makeCompany();
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Band Product',
            'code' => 'BND-'.Str::lower(Str::random(6)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Band Plan',
            'code' => 'BP-'.Str::lower(Str::random(6)),
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $rate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'min_principal' => 1000,
            'max_principal' => 50000,
            'derived_daily_rate' => 0.00926667,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $this->assertEquals(27.8, (float) $rate->term_interest_percentage);
        $this->assertTrue($rate->matchesPrincipalAmount(10000));
        $this->assertFalse($rate->matchesPrincipalAmount(500));
        $this->assertEqualsWithDelta(0.00926667, $rate->effectiveDailyRate(), 0.0000001);
    }

    private function makeCompany(): Company
    {
        $suffix = Str::lower(Str::random(6));

        return Company::create([
            'name' => 'Pricing Test Co '.$suffix,
            'slug' => 'pricing-test-'.$suffix,
            'code' => 'PTC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }
}
