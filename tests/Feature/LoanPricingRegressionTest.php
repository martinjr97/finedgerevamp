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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Full pipeline: rate import → priced loan → accrual → settlement → UI smoke.
 */
class LoanPricingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_import_through_settlement_pipeline(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->admin(['loan-rate-types.update', 'loan-rate-types.view', 'loans.view', 'loans.disburse']);

        $company = Company::create([
            'name' => 'Regression Co '.$suffix,
            'slug' => 'reg-'.$suffix,
            'code' => 'RC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Regression Product',
            'code' => 'RP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Term Rates',
            'code' => 'TR-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'tenure_months,processing_fee_percentage,term_interest_percentage,arrear_rate,is_active',
            '1,5.00,27.8,0.01,1',
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $rateType),
            ['rates_file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)]
        )->assertRedirect();

        $rate = LoanRate::where('loan_rate_type_id', $rateType->id)->first();
        $this->assertNotNull($rate);
        $this->assertEquals(27.8, (float) $rate->term_interest_percentage);
        $this->assertNotNull($rate->derived_daily_rate);

        $loan = $this->bookLoan($rateType, $rate, $product, $suffix);
        $loan->createPaymentSchedule();

        $this->assertEqualsWithDelta(10500.0, (float) $loan->outstanding_balance, 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $loan->getProjectedTotalAmount(), 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $loan->paymentSchedules()->sum('expected_amount'), 0.01);

        $this->artisan('loans:accrue-interest', ['--date' => '2026-01-02'])->assertSuccessful();
        $loan->refresh();
        $this->assertGreaterThan(10500.0, (float) $loan->outstanding_balance);

        $settlement = app(LoanSettlementService::class);
        $quote = $settlement->quoteSettlement($loan, '2026-01-10');
        $this->assertLessThan(13280.0, (float) $quote['payoff_amount']);

        $settlement->applySettlement($loan, [
            'amount' => $quote['payoff_amount'],
            'settlement_date' => '2026-01-10',
            'channel_id' => $loan->channel_id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-rate-types.show', $rateType))
            ->assertOk()
            ->assertSee('27.8');

        $loan->refresh();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertSee('Financial Summary', false);
    }

    public function test_legacy_loan_without_interest_behavior_renders_admin_show(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->admin(['loans.view']);

        $company = Company::create([
            'name' => 'Legacy Co '.$suffix,
            'slug' => 'leg-'.$suffix,
            'code' => 'LC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Legacy',
            'code' => 'L-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Legacy',
            'last_name' => 'Borrower',
            'email' => 'leg-'.$suffix.'@example.com',
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

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 5000,
            'processing_fee' => 250,
            'interest_accrued' => 1000,
            'total_amount' => 6250,
            'outstanding_balance' => 6250,
            'amount_paid' => 0,
            'tenure_months' => 2,
            'loan_start_date' => '2025-06-01',
            'loan_end_date' => '2025-08-01',
            'first_payment_date' => '2025-07-01',
            'last_payment_date' => '2025-08-01',
            'accrual_type' => 'at_beginning',
            'accrual_period' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertSee('Booked outstanding balance', false);
    }

    private function admin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(4));
        $company = Company::create([
            'name' => 'Admin Co '.$suffix,
            'slug' => 'ac-'.$suffix,
            'code' => 'AC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Reg',
            'last_name' => 'Admin',
            'email' => 'reg-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function bookLoan(LoanRateType $rateType, LoanRate $rate, LoanProduct $product, string $suffix): Loan
    {
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'G',
            'code' => 'G-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $product->company_id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Pipe',
            'last_name' => 'Test',
            'email' => 'pipe-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'name' => 'Ch',
            'code' => 'CH2-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $pricing = app(LoanPricingService::class);
        $start = Carbon::parse('2026-01-01');
        $quote = $pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => 1,
            'start_date' => $start->toDateString(),
            'term_days' => 30,
            'loan_rate' => $rate,
            'loan_rate_type' => $rateType,
            'loan_product' => $product,
        ]);

        $financials = $pricing->buildLoanFinancialSnapshot($quote);
        $meta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'loan_rate_id' => $rate->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 10000,
            'tenure_months' => 1,
            'loan_start_date' => $start,
            'loan_end_date' => $start->copy()->addDays(30),
            'first_payment_date' => $start->copy()->addMonth(),
            'last_payment_date' => $start->copy()->addDays(30),
            'amount_paid' => 0,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
            'last_accrual_date' => $start,
            'metadata' => $meta,
        ], $financials));
    }
}
