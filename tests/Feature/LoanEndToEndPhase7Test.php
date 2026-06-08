<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use App\Services\LoanSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanEndToEndPhase7Test extends TestCase
{
    use RefreshDatabase;

    private LoanPricingService $pricing;

    private LoanSettlementService $settlement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(LoanPricingService::class);
        $this->settlement = app(LoanSettlementService::class);
    }

    public function test_end_to_end_term_rate_upfront_and_daily_accrual_with_settlement(): void
    {
        $admin = $this->makeAdmin(['loans.view', 'loans.disburse']);

        $upfront = $this->createPricedLoan(LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT);
        $upfront->createPaymentSchedule();

        $this->assertEqualsWithDelta(2780.0, (float) $upfront->interest_accrued, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $upfront->processing_fee, 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $upfront->outstanding_balance, 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $upfront->paymentSchedules()->sum('expected_amount'), 0.01);

        $daily = $this->createPricedLoan(LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL);
        $daily->createPaymentSchedule();

        $this->assertEqualsWithDelta(10500.0, (float) $daily->outstanding_balance, 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $daily->getProjectedTotalAmount(), 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $daily->paymentSchedules()->sum('expected_amount'), 0.01);
        $this->assertTrue($daily->scheduleUsesProjectedInterest());

        $beforeOutstanding = (float) $daily->outstanding_balance;

        $this->artisan('loans:accrue-interest', ['--date' => '2026-01-02'])
            ->assertSuccessful();

        $daily->refresh();
        $this->assertGreaterThan($beforeOutstanding, (float) $daily->outstanding_balance);
        $this->assertGreaterThan(0, (float) $daily->interest_accrued);

        $dailyQuote = $this->settlement->quoteSettlement($daily, '2026-01-15');
        $this->assertLessThan(13280.0, (float) $dailyQuote['payoff_amount']);

        $this->settlement->applySettlement($daily, [
            'amount' => $dailyQuote['payoff_amount'],
            'settlement_date' => '2026-01-15',
            'channel_id' => $daily->channel_id,
        ]);

        $daily->refresh();
        $this->assertEquals(0.0, (float) $daily->outstanding_balance);

        $upfrontQuote = $this->settlement->quoteSettlement($upfront, '2026-01-15');
        $this->settlement->applySettlement($upfront, [
            'amount' => $upfrontQuote['payoff_amount'],
            'settlement_date' => '2026-01-15',
            'channel_id' => $upfront->channel_id,
        ]);
        $upfront->refresh();
        $this->assertGreaterThan(0, (float) $upfront->rebate_amount);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $upfront))
            ->assertOk()
            ->assertSee('Financial Summary')
            ->assertSee('Booked outstanding balance')
            ->assertSee('Projected repayment total', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $upfront))
            ->assertOk()
            ->assertSee('Interest rebate', false);

        $dailyActive = $this->createPricedLoan(LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $dailyActive))
            ->assertOk()
            ->assertSee('daily accrual', false)
            ->assertSee('Early Settlement', false);
    }

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'E2E Co '.$suffix,
            'slug' => 'e2e-'.$suffix,
            'code' => 'E2E'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'E2E',
            'last_name' => 'Admin',
            'email' => 'e2e-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function createPricedLoan(string $interestBehavior): Loan
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'E2E Loan '.$suffix,
            'slug' => 'e2e-loan-'.$suffix,
            'code' => 'EL'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Product',
            'code' => 'P-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Term',
            'code' => 'T-'.$suffix,
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
            'derived_daily_rate' => '0.00926667',
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'G',
            'code' => 'G-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Borrower',
            'last_name' => 'E2E',
            'email' => 'b-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'name' => 'Ch',
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-31');

        $quote = $this->pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => 1,
            'start_date' => $start->toDateString(),
            'term_days' => 30,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $product,
        ]);

        $financials = $this->pricing->buildLoanFinancialSnapshot($quote);
        $meta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);
        $meta['term_days'] = 30;

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'loan_rate_id' => $loanRate->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 10000,
            'tenure_months' => 1,
            'loan_start_date' => $start,
            'loan_end_date' => $end,
            'first_payment_date' => $start->copy()->addMonth(),
            'last_payment_date' => $end,
            'amount_paid' => 0,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
            'quoted_term_rate' => 27.8,
            'last_accrual_date' => $interestBehavior === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL ? $start : null,
            'metadata' => $meta,
        ], $financials));
    }
}
