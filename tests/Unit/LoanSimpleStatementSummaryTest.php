<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanAccrual;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanSimpleStatementSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_upfront_loan_statement_shows_booked_interest(): void
    {
        $loan = $this->createPricedLoan(LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT);
        $summary = $loan->getSimpleStatementSummary();

        $this->assertFalse($summary['is_daily_accrual']);
        $this->assertEqualsWithDelta(10000.0, $summary['principal'], 0.01);
        $this->assertEqualsWithDelta(500.0, $summary['processing_fee'], 0.01);
        $this->assertEqualsWithDelta(2780.0, $summary['interest_full_term'], 0.01);
        $this->assertEqualsWithDelta(2780.0, $summary['interest_accrued_to_date'], 0.01);
        $this->assertEqualsWithDelta(0.0, $summary['interest_remaining'], 0.01);
        $this->assertEqualsWithDelta(13280.0, $summary['booked_at_origination'], 0.01);
        $this->assertEqualsWithDelta(13280.0, $summary['total_if_held_to_term'], 0.01);
    }

    public function test_daily_accrual_statement_splits_accrued_and_remaining(): void
    {
        $loan = $this->createPricedLoan(LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL);
        $loan->update([
            'status' => 'active',
            'disbursement_status' => 'completed',
            'loan_start_date' => Carbon::parse('2026-01-01'),
            'loan_end_date' => Carbon::parse('2026-01-31'),
        ]);

        LoanAccrual::create([
            'loan_id' => $loan->id,
            'accrual_date' => '2026-01-10',
            'principal_balance' => 10000,
            'interest_amount' => 100,
            'cumulative_interest' => 100,
            'total_balance' => 10600,
            'accrual_period' => 'daily',
            'rate_used' => 0.01,
        ]);

        $loan->load('accruals');
        $summary = $loan->getSimpleStatementSummary();

        $this->assertTrue($summary['is_daily_accrual']);
        $this->assertEqualsWithDelta(100.0, $summary['interest_accrued_to_date'], 0.01);
        $this->assertEqualsWithDelta(2780.0, $summary['interest_full_term'], 0.01);
        $this->assertEqualsWithDelta(2680.0, $summary['interest_remaining'], 0.01);
        $this->assertEqualsWithDelta(10500.0, $summary['booked_at_origination'], 0.01);
        $this->assertEqualsWithDelta(13280.0, $summary['total_if_held_to_term'], 0.01);
    }

    private function createPricedLoan(string $interestBehavior): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $pricing = app(LoanPricingService::class);

        $company = Company::create([
            'name' => 'Stmt Co '.$suffix,
            'slug' => 'stmt-'.$suffix,
            'code' => 'ST'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Stmt Product',
            'code' => 'SP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate',
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => $interestBehavior,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Group',
            'code' => 'G-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Stmt',
            'last_name' => 'Borrower',
            'email' => 'stmt-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $loanStartDate = Carbon::parse('2026-01-01');
        $loanEndDate = $loanStartDate->copy()->addMonth();
        $days = $loanStartDate->diffInDays($loanEndDate);

        $quote = $pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => 1,
            'start_date' => $loanStartDate->toDateString(),
            'term_days' => $days,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $product,
        ]);

        $financials = $pricing->buildLoanFinancialSnapshot($quote);
        $pricingMeta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'loan_rate_id' => $loanRate->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 10000,
            'tenure_months' => 1,
            'loan_start_date' => $loanStartDate,
            'loan_end_date' => $loanEndDate,
            'first_payment_date' => $loanEndDate,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'metadata' => $pricingMeta,
        ], $financials));
    }
}
