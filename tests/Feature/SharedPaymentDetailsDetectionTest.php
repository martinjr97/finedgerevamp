<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerPaymentDetail;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\SharedPaymentDetailsDetectionService;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedPaymentDetailsDetectionTest extends TestCase
{
    use RefreshDatabase;

  /**
     * @return array{customerA: Customer, customerB: Customer, product: LoanProduct}
     */
    private function loanContext(): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Shared Pay Co '.$suffix,
            'slug' => 'shared-pay-'.$suffix,
            'code' => 'SPC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character '.$suffix,
            'code' => 'CHR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate '.$suffix,
            'code' => 'R-'.$suffix,
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'daily_rate' => 0.03,
            'arrear_rate' => 0.03,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Group '.$suffix,
            'code' => 'G-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customerA = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Alice',
            'last_name' => 'One',
            'email' => 'alice.'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $customerB = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Bob',
            'last_name' => 'Two',
            'email' => 'bob.'.$suffix.'@example.com',
            'phone' => '260971111111',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return [
            'customerA' => $customerA,
            'customerB' => $customerB,
            'product' => $product,
        ];
    }

    private function makeLoan(Customer $customer, LoanProduct $product, Channel $channel, array $destination = []): Loan
    {
        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 1000,
            'outstanding_balance' => 1000,
            'tenure_months' => 1,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'approved',
            'disbursement_status' => 'pending',
        ], $destination));
    }

    public function test_detects_same_wallet_phone_on_another_loan(): void
    {
        $context = $this->loanContext();
        $channel = Channel::create([
            'name' => 'MTN '.$context['product']->code,
            'code' => 'MTN_'.$context['product']->code,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $sharedPhone = '260972222222';

        $this->makeLoan($context['customerB'], $context['product'], $channel, [
            'disbursement_phone_number' => $sharedPhone,
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
        ]);

        $loanA = $this->makeLoan($context['customerA'], $context['product'], $channel, [
            'disbursement_phone_number' => $sharedPhone,
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
        ]);

        $result = app(SharedPaymentDetailsDetectionService::class)->forLoan($loanA);

        $this->assertTrue($result['has_matches']);
        $this->assertSame(1, $result['total_count']);
        $this->assertSame($context['customerB']->id, $result['matches'][0]['customer_id']);
        $this->assertSame('loan', $result['matches'][0]['source']);
    }

    public function test_detects_same_bank_account_on_customer_payment_profile(): void
    {
        $this->seed(FinancialInstitutionSeeder::class);
        $context = $this->loanContext();
        $institution = FinancialInstitution::where('code', 'ZANACO')->firstOrFail();
        $branch = $institution->branches()->where('name', 'Main Branch')->firstOrFail();

        $channel = Channel::create([
            'name' => 'Bank '.$context['product']->code,
            'code' => 'BNK_'.$context['product']->code,
            'type' => Channel::TYPE_BANK,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        CustomerPaymentDetail::create([
            'customer_id' => $context['customerB']->id,
            'method_type' => 'bank',
            'bank_financial_institution_id' => $institution->id,
            'bank_financial_institution_branch_id' => $branch->id,
            'account_name' => 'BOB TWO',
            'account_number' => '9988776655',
        ]);

        $loanA = $this->makeLoan($context['customerA'], $context['product'], $channel, [
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'ALICE ONE',
            'disbursement_account_number' => '9988776655',
        ]);

        $result = app(SharedPaymentDetailsDetectionService::class)->forLoan($loanA);

        $this->assertTrue($result['has_matches']);
        $this->assertSame($context['customerB']->id, $result['matches'][0]['customer_id']);
        $this->assertSame('customer_payment_profile', $result['matches'][0]['source']);
    }

    public function test_loan_show_page_displays_shared_payment_alert(): void
    {
        $context = $this->loanContext();
        $admin = \App\Models\Admin::create([
            'company_id' => $context['customerA']->company_id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.Str::random(6).'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'loans.show', 'guard_name' => 'admin']);
        $admin->givePermissionTo('loans.show');

        $channel = Channel::create([
            'name' => 'MTN Alert',
            'code' => 'MTNA',
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $phone = '260973333333';
        $this->makeLoan($context['customerB'], $context['product'], $channel, [
            'disbursement_phone_number' => $phone,
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
        ]);

        $loanA = $this->makeLoan($context['customerA'], $context['product'], $channel, [
            'disbursement_phone_number' => $phone,
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loanA));

        $response->assertOk();
        $response->assertSee('Shared payment credentials detected', false);
        $response->assertSee($context['customerB']->full_name, false);
    }
}
