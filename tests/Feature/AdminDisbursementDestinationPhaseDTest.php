<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\Wallet;
use App\Services\DisbursementDestinationService;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminDisbursementDestinationPhaseDTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Phase D Co '.$suffix,
            'slug' => 'phase-d-co-'.$suffix,
            'code' => 'PDC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Phase',
            'last_name' => 'Admin',
            'email' => 'phase-d-'.$suffix.'@example.com',
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

    private function characterLoanContext(): array
    {
        $admin = $this->adminWithPermissions(['loans.create']);
        $suffix = Str::lower(Str::random(6));

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Product',
            'code' => 'CHR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Character Rate',
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
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return compact('admin', 'product', 'customer', 'rateType', 'loanRate', 'group');
    }

    private function sessionForContext(array $context, Channel $channel, array $destination = []): array
    {
        return $this->baseSessionPayload($channel, array_merge([
            'loan_rate_id' => $context['loanRate']->id,
        ], $destination));
    }

    private function channel(string $type, string $suffix): Channel
    {
        return Channel::create([
            'name' => ucfirst(str_replace('_', ' ', $type)).' '.$suffix,
            'code' => strtoupper($type).'_'.$suffix,
            'type' => $type,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{institution: FinancialInstitution, branch: FinancialInstitutionBranch, otherBranch: FinancialInstitutionBranch}
     */
    private function bankFixtures(): array
    {
        $this->seed(FinancialInstitutionSeeder::class);
        $institution = FinancialInstitution::where('code', 'ZANACO')->firstOrFail();
        $branch = $institution->branches()->where('name', 'Main Branch')->firstOrFail();
        $otherInstitution = FinancialInstitution::where('code', 'FNB')->firstOrFail();
        $otherBranch = $otherInstitution->branches()->firstOrFail();

        return compact('institution', 'branch', 'otherBranch');
    }

    private function baseSessionPayload(Channel $channel, array $destination = []): array
    {
        return array_merge([
            'loan_amount' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'days' => 90,
            'loan_rate_id' => LoanRate::first()->id,
            'daily_rate' => 0.03,
            'weekly_rate' => null,
            'processing_fee' => 250,
            'interest' => 450,
            'total_amount' => 5700,
            'accrual_period' => 'daily',
            'channel_id' => $channel->id,
        ], $destination);
    }

    public function test_admin_loan_creation_with_mobile_wallet_stores_phone_destination(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $session = $this->sessionForContext($context, $channel, [
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $session])
            ->post(route('admin.loan-applications.store-character', [$context['product'], $context['customer']]));

        $response->assertSessionMissing('error');
        $response->assertRedirect();
        $loan = Loan::query()->latest('id')->first();
        $this->assertNotNull($loan);
        $this->assertTrue($loan->hasMobileWalletDestination());
        $this->assertSame('260978232334', $loan->disbursement_phone_number);
        $this->assertNull($loan->disbursement_financial_institution_id);
    }

    public function test_admin_loan_creation_with_bank_stores_bank_destination_and_clears_phone(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $service = app(DisbursementDestinationService::class);
        $normalized = $service->validateAndNormalize([
            'channel_id' => $channel->id,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $this->sessionForContext($context, $channel, $normalized)])
            ->post(route('admin.loan-applications.store-character', [$context['product'], $context['customer']]));

        $response->assertRedirect();
        $loan = Loan::query()->latest('id')->first();
        $this->assertTrue($loan->hasBankDestination());
        $this->assertNull($loan->disbursement_phone_number);
        $this->assertSame('Jane Banda', $loan->disbursement_account_holder_name);
    }

    public function test_admin_loan_creation_with_cash_clears_phone_and_bank_fields(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_CASH, Str::upper(Str::random(4)));

        $service = app(DisbursementDestinationService::class);
        $normalized = $service->validateAndNormalize([
            'channel_id' => $channel->id,
            'disbursement_notes' => 'Collect at branch counter',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $this->sessionForContext($context, $channel, $normalized)])
            ->post(route('admin.loan-applications.store-character', [$context['product'], $context['customer']]));

        $response->assertRedirect();
        $loan = Loan::query()->latest('id')->first();
        $this->assertTrue($loan->hasCashDestination());
        $this->assertNull($loan->disbursement_phone_number);
        $this->assertNull($loan->disbursement_financial_institution_id);
        $this->assertSame('Collect at branch counter', $loan->disbursement_notes);
    }

    public function test_store_calculation_rejects_bank_branch_from_other_institution(): void
    {
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));
        ['institution' => $institution, 'otherBranch' => $otherBranch] = $this->bankFixtures();

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession([
                'loan_application_data' => $this->sessionForContext($context, $channel),
            ])
            ->postJson(
                route('admin.loan-applications.store-calculation', [$context['product'], $context['customer']]),
                [
                    'include_destination' => true,
                    'channel_id' => $channel->id,
                    'disbursement_financial_institution_id' => $institution->id,
                    'disbursement_financial_institution_branch_id' => $otherBranch->id,
                    'disbursement_account_holder_name' => 'Jane Banda',
                    'disbursement_account_number' => '1234567890',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('disbursement_financial_institution_branch_id');
    }

    public function test_store_calculation_rejects_invalid_mobile_wallet_phone(): void
    {
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession([
                'loan_application_data' => $this->sessionForContext($context, $channel),
            ])
            ->postJson(
                route('admin.loan-applications.store-calculation', [$context['product'], $context['customer']]),
                [
                    'include_destination' => true,
                    'channel_id' => $channel->id,
                    'disbursement_phone_number' => '0978232334',
                ]
            );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('disbursement_phone_number');
    }

    public function test_admin_loan_show_displays_destination_summary(): void
    {
        $admin = $this->adminWithPermissions(['loans.show', 'loans.create']);
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $loan = Loan::create([
            'customer_id' => $context['customer']->id,
            'loan_product_id' => $context['product']->id,
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
            'status' => 'pending_approval',
            'disbursement_status' => 'pending',
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
            'disbursement_destination_snapshot' => [
                'channel_name' => $channel->name,
                'channel_type' => Channel::TYPE_MOBILE_WALLET,
                'disbursement_phone_number' => '260978232334',
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan));

        $response->assertOk();
        $response->assertSee('Destination Summary');
        $response->assertSee('260978232334');
    }

    public function test_payment_detail_update_can_switch_from_mobile_wallet_to_bank(): void
    {
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();
        $walletChannel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'W1');
        $bankChannel = $this->channel(Channel::TYPE_BANK, 'B1');
        $loan = $this->loanForPaymentChange($walletChannel, [
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
        ]);

        $admin = $this->adminWithPermissions(['loans.update-payment-details']);

        $this->assertDatabaseHas('loans', ['id' => $loan->id]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.payment-details', $loan), [
            'channel_id' => $bankChannel->id,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
            'payment_change_reason' => 'Customer requested bank payout',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));
        $response->assertSessionDoesntHaveErrors();
        if ($response->getSession()->has('error')) {
            $this->fail((string) $response->getSession()->get('error'));
        }
        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
        $loan->refresh();
        $this->assertSame(Channel::TYPE_BANK, $loan->disbursement_channel_type);
        $this->assertTrue($loan->hasBankDestination());
        $this->assertNull($loan->disbursement_phone_number);
        $this->assertSame($bankChannel->id, $loan->channel_id);
    }

    public function test_payment_detail_update_can_switch_from_bank_to_mobile_wallet(): void
    {
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();
        $walletChannel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'W2');
        $bankChannel = $this->channel(Channel::TYPE_BANK, 'B2');
        $loan = $this->loanForPaymentChange($bankChannel, [
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $admin = $this->adminWithPermissions(['loans.update-payment-details']);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.payment-details', $loan), [
            'channel_id' => $walletChannel->id,
            'disbursement_phone_number' => '260955000222',
            'payment_change_reason' => 'Customer switched back to mobile wallet',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));
        $loan->refresh();
        $this->assertTrue($loan->hasMobileWalletDestination());
        $this->assertSame('260955000222', $loan->disbursement_phone_number);
        $this->assertNull($loan->disbursement_financial_institution_id);
    }

    public function test_payment_detail_update_to_cash_clears_phone_and_bank_fields(): void
    {
        $walletChannel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'W3');
        $cashChannel = $this->channel(Channel::TYPE_CASH, 'C3');
        $loan = $this->loanForPaymentChange($walletChannel, [
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
        ]);

        $admin = $this->adminWithPermissions(['loans.update-payment-details']);

        $this->actingAs($admin, 'admin')->post(route('admin.loans.payment-details', $loan), [
            'channel_id' => $cashChannel->id,
            'disbursement_notes' => 'Cash pickup',
            'payment_change_reason' => 'Customer will collect cash',
        ]);

        $loan->refresh();
        $this->assertTrue($loan->hasCashDestination());
        $this->assertNull($loan->disbursement_phone_number);
        $this->assertNull($loan->disbursement_account_number);
    }

    public function test_legacy_mobile_wallet_session_still_creates_loan(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->characterLoanContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'LEG');

        $session = $this->sessionForContext($context, $channel, [
            'disbursement_phone_number' => '260978232334',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $session])
            ->post(route('admin.loan-applications.store-character', [$context['product'], $context['customer']]));

        $response->assertRedirect();
        $loan = Loan::query()->latest('id')->first();
        $this->assertSame('260978232334', $loan->disbursement_phone_number);
        $this->assertTrue($loan->hasMobileWalletDestination());
    }

    public function test_treasury_manual_disbursement_source_remains_separate_from_customer_destination(): void
    {
        config(['app.disbursement_type' => 'manual', 'approval.loans.create' => false]);
        $admin = $this->adminWithPermissions(['loans.disburse', 'loans.update-payment-details']);
        $walletChannel = $this->channel(Channel::TYPE_MOBILE_WALLET, 'TD');
        $loan = $this->loanForPaymentChange($walletChannel, [
            'status' => 'approved',
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
        ]);

        $treasuryWallet = Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => '260955111222',
            'current_balance' => 50000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.disburse', $loan), [
            'source_type' => 'wallet',
            'source_id' => $treasuryWallet->id,
            'reference_number' => 'DISB-001',
            'disbursement_date' => now()->toDateString(),
            'description' => 'Manual disbursement',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));
        $loan->refresh();
        $this->assertSame('wallet', $loan->disbursed_via_type);
        $this->assertSame($treasuryWallet->id, (int) $loan->disbursed_via_id);
        $this->assertSame('260978232334', $loan->disbursement_phone_number);
        $this->assertSame('completed', $loan->disbursement_status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function loanForPaymentChange(Channel $channel, array $overrides = []): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::first() ?? Company::create([
            'name' => 'Payment Co',
            'slug' => 'payment-co-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Payment Product',
            'code' => 'PP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Pay',
            'last_name' => 'Customer',
            'email' => 'pay-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 5000,
            'outstanding_balance' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'pending_approval',
            'disbursement_status' => 'pending',
        ], $overrides));
    }
}
