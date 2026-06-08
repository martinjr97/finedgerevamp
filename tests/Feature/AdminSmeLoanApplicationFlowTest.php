<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminSmeLoanApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithLoanCreatePermission(): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'SME Test Co '.$suffix,
            'slug' => 'sme-test-co-'.$suffix,
            'code' => 'SME'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Loan',
            'last_name' => 'Admin',
            'email' => 'loan-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'loans.create', 'guard_name' => 'admin']);
        $admin->givePermissionTo('loans.create');

        return $admin;
    }

    /**
     * @return array{loanProduct: LoanProduct, customer: Customer, loanRate: LoanRate, channel: Channel}
     */
    private function createSmeFlowContext(Admin $admin): array
    {
        $suffix = Str::lower(Str::random(6));

        $loanProduct = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'SME Working Capital '.$suffix,
            'code' => 'SME-PROD-'.$suffix,
            'category' => 'sme',
            'max_amount' => 50000,
            'is_active' => true,
        ]);

        $loanRateType = LoanRateType::create([
            'loan_product_id' => $loanProduct->id,
            'name' => 'SME Daily Rate '.$suffix,
            'code' => 'SME-RT-'.$suffix,
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        $admin->company->update([
            'loan_rate_type_id' => $loanRateType->id,
            'maximum_loan_tenure_months' => 12,
            'monthly_cut_off_day' => 20,
            'pay_day' => 30,
            'maximum_debit_ratio' => 60,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $loanRateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'daily_rate' => 0.03,
            'weekly_rate' => null,
            'arrear_rate' => 0.03,
            'is_active' => true,
        ]);

        $channel = Channel::create([
            'name' => 'Mobile Money '.$suffix,
            'code' => 'MM-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $loanProduct->id,
            'customer_type' => 'company',
            'registered_name' => 'Bluebird Traders '.$suffix,
            'first_name' => 'Bluebird',
            'last_name' => 'Traders',
            'email' => 'sme-customer-'.$suffix.'@example.com',
            'password' => '1234',
            'phone' => '260955'.random_int(100000, 999999),
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 15000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return [
            'loanProduct' => $loanProduct,
            'customer' => $customer,
            'loanRate' => $loanRate,
            'channel' => $channel,
        ];
    }

    public function test_sme_loan_details_shows_continue_to_review_cta(): void
    {
        $admin = $this->makeAdminWithLoanCreatePermission();
        $context = $this->createSmeFlowContext($admin);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.loan-details', [$context['loanProduct'], $context['customer']]));

        $response->assertOk();
        $response->assertSee('Continue to Review');
        $response->assertDontSee('Continue to Collateral');
    }

    public function test_sme_review_route_is_accessible_after_loan_details(): void
    {
        $admin = $this->makeAdminWithLoanCreatePermission();
        $context = $this->createSmeFlowContext($admin);

        $loanData = [
            'loan_amount' => 9000,
            'tenure_months' => 3,
            'loan_start_date' => '2026-03-06',
            'loan_end_date' => '2026-06-06',
            'days' => 92,
            'loan_rate_id' => $context['loanRate']->id,
            'daily_rate' => 0.03,
            'weekly_rate' => null,
            'processing_fee' => 450,
            'interest' => 810,
            'total_amount' => 10260,
            'channel_id' => $context['channel']->id,
            'disbursement_phone_number' => $context['customer']->phone,
        ];

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['loan_application_data' => $loanData])
            ->get(route('admin.loan-applications.review', [$context['loanProduct'], $context['customer']]));

        $response->assertOk();
        $response->assertSee('Review Loan Application');
        $response->assertSee($context['channel']->name);
        $response->assertSeeText('Create Loan');
    }

    public function test_sme_repayment_calculation_uses_start_date_plus_tenure_months(): void
    {
        $admin = $this->makeAdminWithLoanCreatePermission();
        $context = $this->createSmeFlowContext($admin);

        $loanStartDate = '2026-03-06';
        $tenureMonths = 3;
        $expectedEndDate = Carbon::parse($loanStartDate)->addMonths($tenureMonths);

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('admin.loan-applications.calculate-repayment', [$context['loanProduct'], $context['customer']]),
            [
                'loan_amount' => 9000,
                'tenure_months' => $tenureMonths,
                'loan_start_date' => $loanStartDate,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('loan_start_date', $loanStartDate);
        $response->assertJsonPath('loan_end_date', $expectedEndDate->toDateString());
        $response->assertJsonPath('days', (int) Carbon::parse($loanStartDate)->diffInDays($expectedEndDate));
    }

    public function test_sme_store_sets_monthly_repayment_dates_from_loan_start_date(): void
    {
        config(['approval.loans.create' => false]);

        $admin = $this->makeAdminWithLoanCreatePermission();
        $context = $this->createSmeFlowContext($admin);

        $loanStartDate = Carbon::parse('2026-03-06');
        $tenureMonths = 3;
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);

        $loanData = [
            'loan_amount' => 9000,
            'tenure_months' => $tenureMonths,
            'loan_start_date' => $loanStartDate->toDateString(),
            'loan_end_date' => $loanEndDate->toDateString(),
            'days' => $loanStartDate->diffInDays($loanEndDate),
            'loan_rate_id' => $context['loanRate']->id,
            'daily_rate' => 0.03,
            'weekly_rate' => null,
            'processing_fee' => 450,
            'interest' => 810,
            'total_amount' => 10260,
            'channel_id' => $context['channel']->id,
            'disbursement_phone_number' => $context['customer']->phone,
        ];

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['loan_application_data' => $loanData])
            ->post(route('admin.loan-applications.store-mou', [$context['loanProduct'], $context['customer']]));

        $response->assertRedirect();

        $loan = Loan::query()->where('customer_id', $context['customer']->id)->latest('id')->first();
        $this->assertNotNull($loan);
        $this->assertSame($loanStartDate->copy()->addMonth()->toDateString(), $loan->first_payment_date?->toDateString());
        $this->assertSame($loanEndDate->toDateString(), $loan->loan_end_date?->toDateString());
        $this->assertSame($loanEndDate->toDateString(), $loan->last_payment_date?->toDateString());
        $this->assertSame('admin_application_sme', data_get($loan->metadata, 'created_via'));
    }
}
