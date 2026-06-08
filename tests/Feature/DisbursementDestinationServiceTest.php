<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\DisbursementDestinationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DisbursementDestinationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DisbursementDestinationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DisbursementDestinationService::class);
    }

    private function mobileWalletChannel(): Channel
    {
        return Channel::create([
            'name' => 'MTN Money',
            'code' => 'MTN_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
    }

    private function bankChannel(): Channel
    {
        return Channel::create([
            'name' => 'Bank Transfer',
            'code' => 'BANK_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_BANK,
            'can_disburse' => true,
            'can_repay' => false,
            'is_active' => true,
        ]);
    }

    private function cashChannel(): Channel
    {
        return Channel::create([
            'name' => 'Cash',
            'code' => 'CASH_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_CASH,
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
        $institution = FinancialInstitution::create([
            'name' => 'Zanaco',
            'code' => 'ZANACO',
            'is_active' => true,
        ]);

        $otherInstitution = FinancialInstitution::create([
            'name' => 'FNB Zambia',
            'code' => 'FNB',
            'is_active' => true,
        ]);

        $branch = FinancialInstitutionBranch::create([
            'financial_institution_id' => $institution->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $otherBranch = FinancialInstitutionBranch::create([
            'financial_institution_id' => $otherInstitution->id,
            'name' => 'Cairo Road',
            'code' => 'CAIRO',
            'is_active' => true,
        ]);

        return compact('institution', 'branch', 'otherBranch');
    }

    public function test_mobile_wallet_validation_requires_valid_phone(): void
    {
        $channel = $this->mobileWalletChannel();

        $validated = $this->service->validate([
            'channel_id' => $channel->id,
            'disbursement_phone_number' => '260978232334',
        ]);

        $this->assertSame('260978232334', $validated['disbursement_phone_number']);
    }

    public function test_mobile_wallet_validation_rejects_invalid_phone(): void
    {
        $channel = $this->mobileWalletChannel();

        $this->expectException(ValidationException::class);

        $this->service->validate([
            'channel_id' => $channel->id,
            'disbursement_phone_number' => '0978232334',
        ]);
    }

    public function test_bank_validation_requires_institution_branch_and_account_fields(): void
    {
        $channel = $this->bankChannel();
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        try {
            $this->service->validate([
                'channel_id' => $channel->id,
                'disbursement_financial_institution_id' => $institution->id,
            ]);
            $this->fail('Expected validation to fail when branch and account fields are missing.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('disbursement_financial_institution_branch_id', $e->errors());
        }

        $validated = $this->service->validate([
            'channel_id' => $channel->id,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $this->assertSame($institution->id, $validated['disbursement_financial_institution_id']);
        $this->assertSame($branch->id, $validated['disbursement_financial_institution_branch_id']);
    }

    public function test_bank_validation_rejects_branch_from_different_institution(): void
    {
        $channel = $this->bankChannel();
        ['institution' => $institution, 'otherBranch' => $otherBranch] = $this->bankFixtures();

        $this->expectException(ValidationException::class);

        $this->service->validate([
            'channel_id' => $channel->id,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $otherBranch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);
    }

    public function test_cash_validation_accepts_no_phone_or_bank_fields(): void
    {
        $channel = $this->cashChannel();

        $validated = $this->service->validate([
            'channel_id' => $channel->id,
            'disbursement_notes' => 'Collect at head office',
        ]);

        $this->assertSame('Collect at head office', $validated['disbursement_notes']);
        $this->assertArrayNotHasKey('disbursement_phone_number', $validated);
    }

    public function test_normalize_sets_disbursement_channel_type(): void
    {
        $channel = $this->bankChannel();
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $validated = $this->service->validate([
            'channel_id' => $channel->id,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
        ]);

        $normalized = $this->service->normalize($validated, $channel);

        $this->assertSame(Channel::TYPE_BANK, $normalized['disbursement_channel_type']);
    }

    public function test_normalize_clears_irrelevant_fields_per_channel_type(): void
    {
        $wallet = $this->mobileWalletChannel();
        $bank = $this->bankChannel();
        $cash = $this->cashChannel();
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $walletNormalized = $this->service->normalize(
            $this->service->validate([
                'channel_id' => $wallet->id,
                'disbursement_phone_number' => '260978232334',
            ]),
            $wallet
        );

        $this->assertSame('260978232334', $walletNormalized['disbursement_phone_number']);
        $this->assertNull($walletNormalized['disbursement_financial_institution_id']);
        $this->assertNull($walletNormalized['disbursement_account_number']);

        $bankNormalized = $this->service->normalize(
            $this->service->validate([
                'channel_id' => $bank->id,
                'disbursement_financial_institution_id' => $institution->id,
                'disbursement_financial_institution_branch_id' => $branch->id,
                'disbursement_account_holder_name' => 'Jane Banda',
                'disbursement_account_number' => '1234567890',
            ]),
            $bank
        );

        $this->assertNull($bankNormalized['disbursement_phone_number']);
        $this->assertSame('Jane Banda', $bankNormalized['disbursement_account_holder_name']);

        $cashNormalized = $this->service->normalize(
            $this->service->validate([
                'channel_id' => $cash->id,
            ]),
            $cash
        );

        $this->assertNull($cashNormalized['disbursement_phone_number']);
        $this->assertNull($cashNormalized['disbursement_financial_institution_id']);
        $this->assertNull($cashNormalized['disbursement_account_number']);
    }

    public function test_snapshot_stores_bank_institution_and_branch_names(): void
    {
        $channel = $this->bankChannel();
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $normalized = $this->service->normalize(
            $this->service->validate([
                'channel_id' => $channel->id,
                'disbursement_financial_institution_id' => $institution->id,
                'disbursement_financial_institution_branch_id' => $branch->id,
                'disbursement_account_holder_name' => 'Jane Banda',
                'disbursement_account_number' => '1234567890',
            ]),
            $channel
        );

        $snapshot = $normalized['disbursement_destination_snapshot'];

        $this->assertSame('Zanaco', $snapshot['financial_institution_name']);
        $this->assertSame('ZANACO', $snapshot['financial_institution_code']);
        $this->assertSame('Main Branch', $snapshot['branch_name']);
        $this->assertSame('MAIN', $snapshot['branch_code']);
        $this->assertSame('Jane Banda', $snapshot['account_holder_name']);
        $this->assertSame('******7890', $snapshot['masked_account_number']);
    }

    public function test_loan_helper_summary_works_for_mobile_wallet(): void
    {
        $loan = $this->makeLoan($this->mobileWalletChannel(), [
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260978232334',
            'disbursement_destination_snapshot' => [
                'channel_name' => 'MTN Money',
                'channel_type' => Channel::TYPE_MOBILE_WALLET,
                'disbursement_phone_number' => '260978232334',
            ],
        ]);

        $this->assertTrue($loan->hasMobileWalletDestination());
        $this->assertSame('MTN Money · 260978232334', $loan->disbursementDestinationLabel());
        $this->assertStringContainsString('260978232334', $loan->disbursementDestinationSummary());
    }

    public function test_loan_helper_summary_works_for_bank_with_masked_account(): void
    {
        $channel = $this->bankChannel();
        ['institution' => $institution, 'branch' => $branch] = $this->bankFixtures();

        $loan = $this->makeLoan($channel, [
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_financial_institution_branch_id' => $branch->id,
            'disbursement_account_holder_name' => 'Jane Banda',
            'disbursement_account_number' => '1234567890',
            'disbursement_destination_snapshot' => [
                'channel_name' => 'Bank Transfer',
                'channel_type' => Channel::TYPE_BANK,
                'financial_institution_name' => 'Zanaco',
                'branch_name' => 'Main Branch',
                'account_holder_name' => 'Jane Banda',
                'masked_account_number' => '******7890',
            ],
        ]);

        $this->assertTrue($loan->hasBankDestination());
        $this->assertStringContainsString('Zanaco', $loan->disbursementDestinationSummary());
        $this->assertStringContainsString('******7890', $loan->disbursementDestinationSummary());
        $this->assertStringNotContainsString('1234567890', $loan->disbursementDestinationSummary());
    }

    public function test_old_loan_with_phone_and_no_type_resolves_as_mobile_wallet(): void
    {
        $channel = $this->mobileWalletChannel();

        $loan = $this->makeLoan($channel, [
            'disbursement_channel_type' => null,
            'disbursement_phone_number' => '260955000111',
        ]);

        $loan->unsetRelation('channel');

        $this->assertTrue($loan->hasMobileWalletDestination());
        $this->assertSame(Channel::TYPE_MOBILE_WALLET, $loan->disbursementChannelType());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLoan(Channel $channel, array $overrides = []): Loan
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Disbursement Co '.$suffix,
            'slug' => 'disbursement-co-'.$suffix,
            'code' => 'DSC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Test Product',
            'code' => 'TP-'.Str::upper(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'email' => 'borrower-'.$suffix.'@example.com',
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
            'loan_number' => 'LN-'.Str::upper(Str::random(10)),
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
        ], $overrides));
    }
}
