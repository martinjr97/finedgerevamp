<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\DisbursementDestinationService;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerDisbursementDestinationPhaseETest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{customer: Customer, product: LoanProduct, loanRate: LoanRate, group: CustomerGroup}
     */
    private function customerContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Customer Phase E '.$suffix,
            'slug' => 'cust-phase-e-'.$suffix,
            'code' => 'CPE'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
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
            'name' => 'Group '.$suffix,
            'code' => 'CG-'.$suffix,
            'risk_level' => 'medium',
            'max_loan_tenure_months' => 12,
            'instalment_cross_over_percentage' => 100,
            'is_active' => true,
            'allow_multiple_loans' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Borrower',
            'last_name' => 'Test',
            'email' => 'cust-phase-e-'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'net_salary' => 20000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return compact('customer', 'product', 'loanRate', 'group');
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

    /**
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>
     */
    private function loanApplicationSession(array $context, Channel $channel, array $destination = [], array $extra = []): array
    {
        $normalized = app(DisbursementDestinationService::class)->validateAndNormalize(array_merge([
            'channel_id' => $channel->id,
        ], $destination));

        return array_merge([
            'loan_application.channel_id' => $channel->id,
            'loan_application.amount' => 5000,
            'loan_application.destination_validated' => true,
            'loan_application.disbursement_channel_type' => $normalized['disbursement_channel_type'],
            'loan_application.disbursement_phone_number' => $normalized['disbursement_phone_number'] ?? null,
            'loan_application.disbursement_financial_institution_id' => $normalized['disbursement_financial_institution_id'] ?? null,
            'loan_application.disbursement_financial_institution_branch_id' => $normalized['disbursement_financial_institution_branch_id'] ?? null,
            'loan_application.disbursement_account_holder_name' => $normalized['disbursement_account_holder_name'] ?? null,
            'loan_application.disbursement_account_number' => $normalized['disbursement_account_number'] ?? null,
            'loan_application.disbursement_notes' => $normalized['disbursement_notes'] ?? null,
            'loan_application.disbursement_destination_snapshot' => $normalized['disbursement_destination_snapshot'] ?? null,
            'loan_application.phone_number' => $normalized['disbursement_phone_number'] ?? null,
        ], $extra);
    }

    public function test_mobile_wallet_requires_valid_260_phone(): void
    {
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $response = $this->actingAs($context['customer'], 'customer')
            ->withSession([
                'loan_application.channel_id' => $channel->id,
                'loan_application.amount' => 5000,
            ])
            ->post(route('customer.loans.store-destination'), [
                'channel_id' => $channel->id,
                'disbursement_phone_number' => '0978232334',
            ]);

        $response->assertSessionHasErrors('disbursement_phone_number');
    }

    public function test_mobile_wallet_stores_phone_destination(): void
    {
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $response = $this->actingAs($context['customer'], 'customer')
            ->withSession([
                'loan_application.channel_id' => $channel->id,
                'loan_application.amount' => 5000,
            ])
            ->post(route('customer.loans.store-destination'), [
                'channel_id' => $channel->id,
                'use_profile_phone' => '1',
            ]);

        $response->assertRedirect(route('customer.loans.calculate'));
        $this->assertTrue(session('loan_application.destination_validated'));
        $this->assertSame('260978232334', session('loan_application.disbursement_phone_number'));
    }

    public function test_bank_requires_institution_branch_holder_and_account(): void
    {
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));

        $response = $this->actingAs($context['customer'], 'customer')
            ->withSession([
                'loan_application.channel_id' => $channel->id,
                'loan_application.amount' => 5000,
            ])
            ->post(route('customer.loans.store-destination'), [
                'channel_id' => $channel->id,
            ]);

        $response->assertSessionHasErrors([
            'disbursement_financial_institution_id',
            'disbursement_financial_institution_branch_id',
            'disbursement_account_holder_name',
            'disbursement_account_number',
        ]);
    }

    public function test_bank_rejects_branch_from_other_institution(): void
    {
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));
        ['institution' => $institution, 'otherBranch' => $otherBranch] = $this->bankFixtures();

        $response = $this->actingAs($context['customer'], 'customer')
            ->withSession([
                'loan_application.channel_id' => $channel->id,
                'loan_application.amount' => 5000,
            ])
            ->post(route('customer.loans.store-destination'), [
                'channel_id' => $channel->id,
                'disbursement_financial_institution_id' => $institution->id,
                'disbursement_financial_institution_branch_id' => $otherBranch->id,
                'disbursement_account_holder_name' => 'Jane Banda',
                'disbursement_account_number' => '1234567890',
            ]);

        $response->assertSessionHasErrors('disbursement_financial_institution_branch_id');
    }

    public function test_bank_stores_bank_destination_and_clears_phone(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $session = $this->loanApplicationSession($context, $channel, [
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ], [
            'loan_application.tenure_months' => 3,
            'loan_application.loan_rate_id' => $context['loanRate']->id,
        ]);

        $this->actingAs($context['customer'], 'customer')
            ->withSession($session)
            ->post(route('customer.loans.store'))
            ->assertRedirect(route('customer.dashboard'));

        $loan = Loan::query()->latest('id')->first();
        $this->assertTrue($loan->hasBankDestination());
        $this->assertNull($loan->disbursement_phone_number);
        $this->assertSame('Jane Banda', $loan->disbursement_account_holder_name);
    }

    public function test_cash_stores_cash_destination_and_clears_phone_and_bank(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_CASH, Str::upper(Str::random(4)));

        $session = $this->loanApplicationSession($context, $channel, [
            'disbursement_notes' => 'Collect at counter',
        ], [
            'loan_application.tenure_months' => 3,
            'loan_application.loan_rate_id' => $context['loanRate']->id,
        ]);

        $this->actingAs($context['customer'], 'customer')
            ->withSession($session)
            ->post(route('customer.loans.store'))
            ->assertRedirect(route('customer.dashboard'));

        $loan = Loan::query()->latest('id')->first();
        $this->assertTrue($loan->hasCashDestination());
        $this->assertNull($loan->disbursement_phone_number);
        $this->assertNull($loan->disbursement_financial_institution_id);
        $this->assertSame('Collect at counter', $loan->disbursement_notes);
    }

    public function test_calculate_page_displays_masked_bank_account(): void
    {
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $session = $this->loanApplicationSession($context, $channel, [
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $response = $this->actingAs($context['customer'], 'customer')
            ->withSession($session)
            ->get(route('customer.loans.calculate'));

        $response->assertOk();
        $response->assertSee('******7890', false);
        $response->assertDontSee('1234567890', false);
    }

    public function test_submitted_loan_has_disbursement_destination_snapshot(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $session = $this->loanApplicationSession($context, $channel, [
            'disbursement_phone_number' => '260978232334',
        ], [
            'loan_application.tenure_months' => 3,
            'loan_application.loan_rate_id' => $context['loanRate']->id,
        ]);

        $this->actingAs($context['customer'], 'customer')
            ->withSession($session)
            ->post(route('customer.loans.store'));

        $loan = Loan::query()->latest('id')->first();
        $this->assertIsArray($loan->disbursement_destination_snapshot);
        $this->assertSame('260978232334', $loan->disbursement_destination_snapshot['disbursement_phone_number'] ?? null);
    }

    public function test_legacy_mobile_wallet_only_loan_still_renders_summary(): void
    {
        $context = $this->customerContext();
        $channel = $this->channel(Channel::TYPE_MOBILE_WALLET, Str::upper(Str::random(4)));

        $loan = Loan::create([
            'customer_id' => $context['customer']->id,
            'loan_product_id' => $context['product']->id,
            'customer_group_id' => $context['group']->id,
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
            'disbursement_phone_number' => '260978232334',
        ]);

        $this->assertStringContainsString('260978232334', $loan->disbursementDestinationSummary());
        $this->assertTrue($loan->hasMobileWalletDestination());
    }

    public function test_collateral_flow_supports_bank_destination(): void
    {
        config(['approval.loans.create' => false]);
        $context = $this->collateralCustomerContext();
        $channel = $this->channel(Channel::TYPE_BANK, Str::upper(Str::random(4)));
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $normalized = app(DisbursementDestinationService::class)->validateAndNormalize([
            'channel_id' => $channel->id,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $sessionData = array_merge([
            'loan_amount' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'processing_fee' => 250,
            'interest' => 450,
            'total_amount' => 5700,
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'days' => 90,
            'loan_rate_id' => $context['loanRate']->id,
            'daily_rate' => 0.03,
            'accrual_period' => 'daily',
        ], $normalized);

        $collateralType = $context['collateralType'];

        $this->actingAs($context['customer'], 'customer')
            ->withSession(['collateral_loan_application_data' => $sessionData])
            ->post(route('customer.collateral-loans.store'), [
                'loan_amount' => 5000,
                'tenure_months' => 3,
                'loan_start_date' => now()->toDateString(),
                'channel_id' => $channel->id,
                'collateral_type_id' => $collateralType->id,
                'collateral_value' => $collateralType->min_value,
            ])
            ->assertRedirect(route('customer.dashboard'));

        $loan = Loan::query()->latest('id')->first();
        $this->assertTrue($loan->hasBankDestination());
        $this->assertNotNull($loan->disbursement_destination_snapshot);
    }

    /**
     * @return array{customer: Customer, product: LoanProduct, loanRate: LoanRate, collateralType: \App\Models\CollateralType}
     */
    private function collateralCustomerContext(): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Collateral Co '.$suffix,
            'slug' => 'coll-co-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Collateral Product',
            'code' => 'COL-'.$suffix,
            'category' => 'collateral',
            'is_active' => true,
            'accrual_type' => 'at_beginning',
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Collateral Rate',
            'code' => 'CLR-'.$suffix,
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
            'name' => 'Collateral Group',
            'code' => 'CGC-'.$suffix,
            'risk_level' => 'medium',
            'max_loan_amount' => 100000,
            'is_active' => true,
            'allow_multiple_loans' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Collateral',
            'last_name' => 'Borrower',
            'email' => 'coll-borrower-'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'maximum_loan_take' => 50000,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $collateralType = \App\Models\CollateralType::create([
            'loan_product_id' => $product->id,
            'name' => 'Vehicle',
            'code' => 'VEH-'.$suffix,
            'category' => 'Vehicle',
            'min_value' => 10000,
            'max_value' => 100000,
            'loan_to_value_ratio' => 70,
            'is_active' => true,
        ]);

        return compact('customer', 'product', 'loanRate', 'collateralType');
    }
}
