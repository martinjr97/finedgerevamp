<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerPaymentDetail;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\WalletProvider;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanApplicationLoanDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(array $permissions): \App\Models\Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Loan Details Co '.$suffix,
            'slug' => 'loan-details-co-'.$suffix,
            'code' => 'LDC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = \App\Models\Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Loan',
            'last_name' => 'Admin',
            'email' => 'loan-details-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return array{admin: \App\Models\Admin, product: LoanProduct, customer: Customer, channel: Channel, loanRate: LoanRate}
     */
    private function characterContext(): array
    {
        $admin = $this->adminWithPermissions(['loans.create']);
        $suffix = Str::lower(Str::random(6));

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
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

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Character Group '.$suffix,
            'code' => 'CG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Banda',
            'email' => 'jane.'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $channel = Channel::create([
            'name' => 'MTN Money '.$suffix,
            'code' => 'MTN_'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        return compact('admin', 'product', 'customer', 'channel', 'loanRate');
    }

    public function test_calculate_repayment_accepts_past_loan_start_date(): void
    {
        $context = $this->characterContext();
        $pastStart = now()->subMonths(2)->toDateString();

        $response = $this->actingAs($context['admin'], 'admin')->postJson(
            route('admin.loan-applications.calculate-repayment', [$context['product'], $context['customer']]),
            [
                'loan_amount' => 5000,
                'tenure_months' => 3,
                'loan_start_date' => $pastStart,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('loan_end_date', now()->subMonths(2)->addMonths(3)->toDateString());
    }

    public function test_store_calculation_without_destination_fields_succeeds(): void
    {
        $context = $this->characterContext();

        $response = $this->actingAs($context['admin'], 'admin')->postJson(
            route('admin.loan-applications.store-calculation', [$context['product'], $context['customer']]),
            [
                'loan_amount' => 5000,
                'tenure_months' => 3,
                'loan_start_date' => now()->toDateString(),
                'processing_fee' => 250,
                'interest' => 450,
                'total_amount' => 5700,
                'loan_end_date' => now()->addMonths(3)->toDateString(),
                'days' => 90,
                'loan_rate_id' => $context['loanRate']->id,
                'accrual_period' => 'daily',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull(session('loan_application_data.channel_id'));
    }

    public function test_loan_details_page_prefills_wallet_payment_details(): void
    {
        $context = $this->characterContext();

        $provider = WalletProvider::create([
            'name' => 'MTN',
            'code' => 'MTN',
            'is_active' => true,
        ]);

        CustomerPaymentDetail::create([
            'customer_id' => $context['customer']->id,
            'method_type' => 'wallet',
            'wallet_provider_id' => $provider->id,
            'wallet_provider' => 'MTN',
            'wallet_number' => '260971234567',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.loan-applications.loan-details', [$context['product'], $context['customer']]));

        $response->assertOk();
        $response->assertSee('Loaded this customer’s saved payment details', false);
        $response->assertSee('value="260971234567"', false);
        $response->assertSee('value="'.$context['channel']->id.'"', false);
    }

    public function test_continue_can_save_customer_payment_details_as_default(): void
    {
        $this->seed(FinancialInstitutionSeeder::class);
        $context = $this->characterContext();

        $bankChannel = Channel::create([
            'name' => 'Bank Transfer '.$context['product']->code,
            'code' => 'BANK_'.$context['product']->code,
            'type' => Channel::TYPE_BANK,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $institution = FinancialInstitution::where('code', 'ZANACO')->firstOrFail();
        $branch = $institution->branches()->where('name', 'Main Branch')->firstOrFail();

        $sessionPayload = [
            'loan_amount' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'days' => 90,
            'loan_rate_id' => $context['loanRate']->id,
            'processing_fee' => 250,
            'interest' => 450,
            'total_amount' => 5700,
            'accrual_period' => 'daily',
        ];

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $sessionPayload])
            ->postJson(
                route('admin.loan-applications.store-calculation', [$context['product'], $context['customer']]),
                [
                    'include_destination' => true,
                    'save_customer_payment_details' => true,
                    'channel_id' => $bankChannel->id,
                    'disbursement_financial_institution_id' => $institution->id,
                    'disbursement_financial_institution_branch_id' => $branch->id,
                    'disbursement_account_holder_name' => 'Jane Banda',
                    'disbursement_account_number' => '1234567890',
                ]
            );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('customer_payment_details', [
            'customer_id' => $context['customer']->id,
            'method_type' => 'bank',
            'account_number' => '1234567890',
        ]);
    }
}
