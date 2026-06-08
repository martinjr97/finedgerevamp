<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanConcurrencyAndExposureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     admin: \App\Models\Admin,
     *     product: LoanProduct,
     *     group: CustomerGroup,
     *     customer: Customer,
     *     loanRate: LoanRate,
     *     channel: Channel
     * }
     */
    private function characterContext(array $groupOverrides = [], array $customerOverrides = []): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Concurrency Co '.$suffix,
            'slug' => 'concurrency-co-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = \App\Models\Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Concurrency',
            'last_name' => 'Admin',
            'email' => 'concurrency-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        Permission::firstOrCreate(['name' => 'loans.create', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'loan-products.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['loans.create', 'loan-products.view']);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character Product '.$suffix,
            'code' => 'CHAR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Character Rate '.$suffix,
            'code' => 'CR-'.$suffix,
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'daily_rate' => 0.03,
            'arrear_rate' => 0.03,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create(array_merge([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Character Group '.$suffix,
            'code' => 'CG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ], $groupOverrides));

        $customer = Customer::create(array_merge([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Banda',
            'email' => 'jane.'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 6000,
            'status' => 'active',
            'approval_status' => 'approved',
        ], $customerOverrides));

        $channel = Channel::create([
            'name' => 'MTN Money '.$suffix,
            'code' => 'MTN_'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        return compact('admin', 'product', 'group', 'customer', 'loanRate', 'channel');
    }

    private function makeLoan(Customer $customer, LoanProduct $product, Channel $channel, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'principal_amount' => 4000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 4000,
            'outstanding_balance' => 4000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
        ], $overrides));
    }

    public function test_new_customer_group_defaults_to_single_loan_policy(): void
    {
        $context = $this->characterContext();

        $this->assertFalse($context['group']->fresh()->allow_multiple_loans);
        $this->assertTrue($context['customer']->canTakeAnotherLoan());
    }

    public function test_customer_with_active_loan_cannot_take_another_when_multiple_loans_disabled(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => false]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel']);

        $this->assertFalse($context['customer']->fresh()->canTakeAnotherLoan());
        $this->assertSame(2000.0, $context['customer']->fresh()->getAvailableLoanAmount());
    }

    public function test_admin_loan_details_blocked_when_customer_has_active_loan_and_multiple_not_allowed(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => false]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel']);

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.loan-applications.loan-details', [$context['product'], $context['customer']]));

        $response->assertRedirect(route('admin.loan-applications.search-customer', $context['product']));
        $response->assertSessionHas('error', $context['customer']->loanEligibilityBlockingMessage());
    }

    public function test_customer_portal_uses_centralized_eligibility_message(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => false]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel']);

        $response = $this->actingAs($context['customer'], 'customer')
            ->get(route('customer.loans.select-channel'));

        $response->assertRedirect(route('customer.dashboard'));
        $response->assertSessionHas('error', $context['customer']->loanEligibilityBlockingMessage());
    }

    public function test_admin_calculate_repayment_blocked_same_as_portal(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => false]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel']);

        $response = $this->actingAs($context['admin'], 'admin')->postJson(
            route('admin.loan-applications.calculate-repayment', [$context['product'], $context['customer']]),
            [
                'loan_amount' => 1000,
                'tenure_months' => 3,
                'loan_start_date' => now()->toDateString(),
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('error', $context['customer']->loanEligibilityBlockingMessage());
    }

    public function test_allow_multiple_loans_allows_second_loan_when_exposure_available(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => true]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel'], [
            'outstanding_balance' => 4000,
            'total_amount' => 4000,
            'principal_amount' => 4000,
        ]);

        $customer = $context['customer']->fresh();

        $this->assertTrue($customer->canTakeAnotherLoan());
        $this->assertSame(2000.0, $customer->getAvailableLoanAmount());
    }

    public function test_second_loan_amount_cannot_exceed_available_exposure(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => true]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel'], [
            'outstanding_balance' => 4000,
            'total_amount' => 4000,
            'principal_amount' => 4000,
        ]);

        $overLimit = $this->actingAs($context['admin'], 'admin')->postJson(
            route('admin.loan-applications.calculate-repayment', [$context['product'], $context['customer']]),
            [
                'loan_amount' => 2500,
                'tenure_months' => 3,
                'loan_start_date' => now()->toDateString(),
            ]
        );

        $overLimit->assertStatus(422)->assertJsonFragment([
            'error' => 'Loan amount cannot exceed your maximum qualified amount of 2,000.00. Your available loan amount is 2,000.00.',
        ]);

        $withinLimit = $this->actingAs($context['admin'], 'admin')->postJson(
            route('admin.loan-applications.calculate-repayment', [$context['product'], $context['customer']]),
            [
                'loan_amount' => 2000,
                'tenure_months' => 3,
                'loan_start_date' => now()->toDateString(),
            ]
        );

        $withinLimit->assertOk();
    }

    public function test_settled_loans_do_not_reduce_available_exposure(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => true]);
        $this->makeLoan($context['customer'], $context['product'], $context['channel'], [
            'status' => 'settled',
            'outstanding_balance' => 0,
            'total_amount' => 4000,
            'principal_amount' => 4000,
        ]);

        $customer = $context['customer']->fresh();

        $this->assertSame(6000.0, $customer->getAvailableLoanAmount());
        $this->assertTrue($customer->canTakeAnotherLoan());
    }

    public function test_group_financial_settings_can_enable_multiple_loans(): void
    {
        $context = $this->characterContext(['allow_multiple_loans' => false]);

        $response = $this->actingAs($context['admin'], 'admin')->put(
            route('admin.customer-groups.update-financial', $context['group']),
            [
                'allow_multiple_loans' => '1',
                'max_loan_amount' => $context['group']->max_loan_amount,
            ]
        );

        $response->assertRedirect(route('admin.customer-groups.show', $context['group']));
        $this->assertTrue($context['group']->fresh()->allow_multiple_loans);
    }

    public function test_salary_exposure_example_caps_new_loan_at_two_thousand(): void
    {
        $context = $this->characterContext(
            ['allow_multiple_loans' => true],
            ['maximum_loan_take' => 6000, 'gross_salary' => 10000]
        );

        $this->makeLoan($context['customer'], $context['product'], $context['channel'], [
            'outstanding_balance' => 4000,
            'total_amount' => 4000,
            'principal_amount' => 4000,
        ]);

        $this->assertSame(2000.0, $context['customer']->fresh()->getAvailableLoanAmount());
    }
}
