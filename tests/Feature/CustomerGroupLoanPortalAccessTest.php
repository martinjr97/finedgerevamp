<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerGroupLoanPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     customer: Customer,
     *     relationshipManager: Admin,
     *     loanProduct: LoanProduct,
     *     customerGroup: CustomerGroup
     * }
     */
    private function createGroupLoanCustomerContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Group Portal Co '.$suffix,
            'slug' => 'group-portal-co-'.$suffix,
            'code' => 'GPC-'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Group Loans '.$suffix,
            'code' => 'GLP-'.$suffix,
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $relationshipManager = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Rachel',
            'last_name' => 'Manager',
            'email' => 'relationship-manager-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);

        $customerGroup = CustomerGroup::create([
            'loan_product_id' => $loanProduct->id,
            'relationship_manager_id' => $relationshipManager->id,
            'name' => 'Portal Group '.$suffix,
            'code' => 'PG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
            'allow_multiple_loans' => false,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'customer_group_id' => $customerGroup->id,
            'first_name' => 'Group',
            'last_name' => 'Customer',
            'email' => 'group-customer-'.$suffix.'@example.com',
            'phone' => '260977'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'maximum_loan_take' => 30000,
            'must_change_pin' => false,
        ]);

        return [
            'customer' => $customer,
            'relationshipManager' => $relationshipManager,
            'loanProduct' => $loanProduct,
            'customerGroup' => $customerGroup,
        ];
    }

    public function test_group_loan_customer_cannot_access_self_service_loan_request_routes(): void
    {
        $context = $this->createGroupLoanCustomerContext();
        $customer = $context['customer'];

        $message = 'Loan requests for Group Loans customers are managed by your relationship manager. Please contact your relationship manager to apply.';

        $selectChannelResponse = $this->actingAs($customer, 'customer')
            ->get(route('customer.loans.select-channel'));
        $selectChannelResponse->assertRedirect(route('customer.dashboard'));
        $selectChannelResponse->assertSessionHas('error', $message);

        $storeLoanResponse = $this->actingAs($customer, 'customer')
            ->post(route('customer.loans.store'));
        $storeLoanResponse->assertRedirect(route('customer.dashboard'));
        $storeLoanResponse->assertSessionHas('error', $message);

        $collateralEntryResponse = $this->actingAs($customer, 'customer')
            ->get(route('customer.collateral-loans.loan-details'));
        $collateralEntryResponse->assertRedirect(route('customer.dashboard'));
        $collateralEntryResponse->assertSessionHas('error', $message);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_group_loan_customer_can_access_repayment_and_support_features(): void
    {
        $context = $this->createGroupLoanCustomerContext();
        $customer = $context['customer'];
        $loanProduct = $context['loanProduct'];
        $customerGroup = $context['customerGroup'];

        $channel = Channel::create([
            'name' => 'Wallet '.Str::lower(Str::random(4)),
            'code' => 'WLT-'.Str::upper(Str::random(4)),
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $loanProduct->id,
            'customer_group_id' => $customerGroup->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($loanProduct),
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'total_amount' => 5000,
            'amount_paid' => 0,
            'outstanding_balance' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonths(2)->toDateString(),
            'first_payment_date' => now()->subDays(5)->toDateString(),
            'last_payment_date' => now()->addMonths(2)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_phone_number' => $customer->phone,
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        $repaymentResponse = $this->actingAs($customer, 'customer')
            ->get(route('customer.repayments.select-type'));
        $repaymentResponse->assertOk();
        $repaymentResponse->assertSee('Make a Repayment');

        $supportResponse = $this->actingAs($customer, 'customer')
            ->get(route('customer.support'));
        $supportResponse->assertOk();
        $supportResponse->assertSee('Submit a Support Ticket');
    }

    public function test_group_loan_customer_can_view_relationship_manager_on_dashboard_and_profile(): void
    {
        $context = $this->createGroupLoanCustomerContext();
        $customer = $context['customer'];
        $relationshipManager = $context['relationshipManager'];

        $dashboardResponse = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Group loan requests are handled by your Relationship Manager.');
        $dashboardResponse->assertSee($relationshipManager->full_name);
        $dashboardResponse->assertDontSee(route('customer.loans.select-channel'), false);

        $profileResponse = $this->actingAs($customer, 'customer')
            ->get(route('customer.profile'));
        $profileResponse->assertOk();
        $profileResponse->assertSee('Relationship Manager');
        $profileResponse->assertSee($relationshipManager->full_name);
    }
}
