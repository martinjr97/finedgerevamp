<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayDestinationMapping;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Services\GatewaySelectionService;
use App\PaymentPlatform\Support\CGrateIssuerNameResolver;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\Support\ProcessesQueuedDisbursementJobs;
use Tests\TestCase;

class PaymentGatewayDisbursementTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use ProcessesQueuedDisbursementJobs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'test-user',
            'cgrate.password' => 'test-pass',
            'cgrate.uat.force_disbursement_issuer_name' => false,
            'queue.default' => 'sync',
        ]);
    }

    public function test_manual_disbursement_still_debits_wallet(): void
    {
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET);
        $wallet = $this->makeTreasuryWallet(10000);
        $admin = $this->makeAdmin(['loans.disburse']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.disburse', $context['loan']), [
                'source_type' => 'wallet',
                'source_id' => $wallet->id,
                'reference_number' => 'DISB-MANUAL',
                'disbursement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $wallet->refresh();
        $context['loan']->refresh();
        $this->assertSame(5000.0, (float) $wallet->current_balance);
        $this->assertSame('completed', $context['loan']->disbursement_status);
    }

    public function test_gateway_initiate_completes_loan_when_cgrate_accepts_disbursement(): void
    {
        $wallet = $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('processCashDeposit', $body);
            $this->assertStringNotContainsString('queryCustomerPayment', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-001'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['metadata']['queued'] ?? false);
        $this->assertSame('processing', $context['loan']->fresh()->disbursement_status);

        $this->runQueuedDisbursementJob($context['loan']);

        $wallet->refresh();
        $context['loan']->refresh();

        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertSame('active', $context['loan']->status);
        $this->assertSame(15000.0, (float) $wallet->current_balance);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'direction' => GatewayDirection::Disbursement->value,
            'status' => GatewayAttemptStatus::Confirmed->value,
        ]);

        Http::assertSentCount(1);
    }

    public function test_soap_payload_correctness_for_mobile_money(): void
    {
        $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'AIRTEL_MONEY');

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('processCashDeposit', $body);
            $this->assertStringContainsString('<transactionAmount>5000.00</transactionAmount>', $body);
            $this->assertStringContainsString('<issuerName>Airtel</issuerName>', $body);
            $this->assertStringContainsString('0978232334', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-MM'), 200);
        });

        app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);
        $this->runQueuedDisbursementJob($context['loan']);

        Http::assertSentCount(1);
    }

    public function test_soap_payload_uses_543_issuer_name_when_uat_force_enabled_for_disbursement(): void
    {
        config(['cgrate.uat.force_disbursement_issuer_name' => true]);

        $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'ZAMTEL_MONEY');

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('<issuerName>543</issuerName>', $body);
            $this->assertStringNotContainsString('<issuerName>Zamtel</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-MM-UAT'), 200);
        });

        app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);
        $this->runQueuedDisbursementJob($context['loan']);

        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'issuer_name' => '543',
        ]);

        Http::assertSentCount(1);
    }

    public function test_uat_force_allows_bank_disbursement_without_destination_mapping(): void
    {
        config(['cgrate.uat.force_disbursement_issuer_name' => true]);

        $this->activateGatewayWallet(20000);
        $institution = FinancialInstitution::create([
            'name' => 'Zambia National Commercial Bank',
            'code' => 'ZANACO',
            'is_active' => true,
        ]);

        $context = $this->makeLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'status' => 'approved',
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('<issuerName>543</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-BANK-UAT'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'issuer_name' => '543',
        ]);
    }

    public function test_soap_payload_correctness_for_bank(): void
    {
        $this->activateGatewayWallet(20000);
        $institution = FinancialInstitution::create([
            'name' => 'Zambia National Commercial Bank',
            'code' => 'ZANACO',
            'is_active' => true,
        ]);

        $context = $this->makeLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        PaymentGatewayDestinationMapping::create([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $institution->id,
            'channel_id' => null,
            'gateway_key' => 'issuerName',
            'gateway_value' => 'ZANACO',
            'environment' => null,
            'status' => 'active',
        ]);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('processCashDeposit', $body);
            $this->assertStringContainsString('<customerAccount>1234567890</customerAccount>', $body);
            $this->assertStringContainsString('<issuerName>ZANACO</issuerName>', $body);
            $this->assertStringNotContainsString('Zambia National Commercial Bank', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-BANK'), 200);
        });

        app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);
        $this->runQueuedDisbursementJob($context['loan']);

        Http::assertSentCount(1);
    }

    public function test_wallet_debit_only_on_confirm(): void
    {
        $wallet = $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-TEST-001',
            'provider_reference' => 'FINEDGE-OUT-TEST-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'customer_account' => '0978232334',
            'issuer_name' => 'MTN',
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        app(GatewayIntegrationService::class)->finalizeConfirmedDisbursement($attempt);

        $wallet->refresh();
        $context['loan']->refresh();

        $this->assertSame(15000.0, (float) $wallet->current_balance);
        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertNotNull($context['loan']->metadata['finance_disbursement_posted_at'] ?? null);
    }

    public function test_missing_linked_wallet_blocks_gateway_initiation(): void
    {
        PaymentGateway::query()->where('code', 'cgrate')->update([
            'status' => PaymentGatewayStatus::Active,
        ]);

        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_failed_gateway_leaves_loan_undisbursed(): void
    {
        $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-FAIL-001',
            'provider_reference' => 'FINEDGE-OUT-FAIL-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Failed,
            'response_message' => 'Payout rejected',
        ]);

        $context['loan']->update(['disbursement_status' => 'processing']);
        app(GatewayIntegrationService::class)->handleDisbursementFailure($attempt);

        $context['loan']->refresh();
        $this->assertSame('failed', $context['loan']->disbursement_status);
        $this->assertNotSame('completed', $context['loan']->disbursement_status);
    }

    public function test_pending_attempt_blocks_retry(): void
    {
        $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-PENDING-001',
            'provider_reference' => 'FINEDGE-OUT-PENDING-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Pending,
            'initiated_at' => now(),
        ]);

        $context['loan']->update(['disbursement_status' => 'processing']);

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertFalse($result['success']);
    }

    public function test_idempotent_confirm_does_not_double_debit(): void
    {
        $wallet = $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-IDEM-001',
            'provider_reference' => 'FINEDGE-OUT-IDEM-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $service = app(GatewayIntegrationService::class);
        $service->finalizeConfirmedDisbursement($attempt);
        $service->finalizeConfirmedDisbursement($attempt->fresh());

        $wallet->refresh();
        $this->assertSame(15000.0, (float) $wallet->current_balance);
    }

    public function test_expired_gateway_attempt_marks_loan_failed(): void
    {
        $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-EXP-001',
            'provider_reference' => 'FINEDGE-OUT-EXP-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Pending,
            'initiated_at' => now()->subMinutes(10),
        ]);

        $context['loan']->update(['disbursement_status' => 'processing']);

        app(GatewayIntegrationService::class)->handleStatusResult($attempt, new \App\PaymentPlatform\DTOs\GatewayStatusResult(
            normalizedStatus: 'expired',
            responseMessage: 'Payment window expired.',
        ));

        $attempt->refresh();
        $context['loan']->refresh();

        $this->assertSame(GatewayAttemptStatus::Expired, $attempt->status);
        $this->assertSame('failed', $context['loan']->disbursement_status);
    }

    public function test_zero_treasury_balance_still_completes_gateway_confirmed_disbursement(): void
    {
        $wallet = $this->activateGatewayWallet(0);
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-ZERO-001',
            'provider_reference' => 'FINEDGE-OUT-ZERO-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $context['loan']->update(['disbursement_status' => 'processing']);

        app(GatewayIntegrationService::class)->finalizeConfirmedDisbursement($attempt);

        $wallet->refresh();
        $context['loan']->refresh();

        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertSame(-5000.0, (float) $wallet->current_balance);
        $this->assertTrue($context['loan']->metadata['finance_posted_below_zero_balance'] ?? false);
    }

    public function test_issuer_name_mobile_mapping(): void
    {
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'ZAMTEL_MONEY');
        $resolver = app(CGrateIssuerNameResolver::class);
        $resolved = $resolver->resolveForLoan($context['loan']);

        $this->assertSame('Zamtel', $resolved['issuer_name']);
        $this->assertSame('mobile_money', $resolved['payment_method']);
    }

    public function test_issuer_name_bank_mapping(): void
    {
        $institution = FinancialInstitution::create([
            'name' => 'Stanbic Bank',
            'code' => 'STANBIC',
            'is_active' => true,
        ]);

        $context = $this->makeLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '99887766',
        ]);

        $resolved = app(CGrateIssuerNameResolver::class)->resolveForLoan($context['loan']->fresh());

        $this->assertSame('Stanbic Bank', $resolved['issuer_name']);
        $this->assertSame('99887766', $resolved['customer_account']);
    }

    public function test_manual_fallback_when_gateway_inactive(): void
    {
        $context = $this->makeLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $wallet = $this->makeTreasuryWallet(10000);
        $admin = $this->makeAdmin(['loans.disburse']);

        $selected = app(GatewaySelectionService::class)->selectForDisbursement($context['loan']);
        $this->assertNull($selected);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.disburse', $context['loan']), [
                'source_type' => 'wallet',
                'source_id' => $wallet->id,
                'reference_number' => 'MANUAL-FALLBACK',
                'disbursement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('completed', $context['loan']->fresh()->disbursement_status);
    }

    public function test_cash_destination_blocked_for_gateway(): void
    {
        $this->activateGatewayWallet(20000);
        $context = $this->makeLoanContext(Channel::TYPE_CASH, 'CASH_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_CASH,
        ]);

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cash disbursements', $result['message'] ?? '');
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    /**
     * @return array{company: Company, channel: Channel, customer: Customer, loan: Loan}
     */
    private function makeLoanContext(string $channelType, ?string $channelCode = null): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Disb GW '.$suffix,
            'slug' => 'dgw-'.$suffix,
            'code' => 'D'.$suffix,
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
        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => $channelCode ?? 'CH-'.$suffix,
            'type' => $channelType,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'email' => 'bor-'.$suffix.'@example.com',
            'phone' => '260978232334',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loanData = [
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.$suffix,
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 5000,
            'outstanding_balance' => 5000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'approved',
            'disbursement_status' => 'pending',
            'disbursement_channel_type' => $channelType,
        ];

        if ($channelType === Channel::TYPE_MOBILE_WALLET) {
            $loanData['disbursement_phone_number'] = '260978232334';
        }

        $loan = Loan::create($loanData);

        return compact('company', 'channel', 'customer', 'loan');
    }

    private function makeTreasuryWallet(float $balance): Wallet
    {
        return Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function activateGatewayWallet(float $balance): Wallet
    {
        $wallet = $this->makeTreasuryWallet($balance);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id);
        $this->enablePaymentGatewayRoute(GatewayRouteKey::BankDisbursement, $gateway->id);

        return $wallet;
    }

    /**
     * @param  array<string, string>  $responses
     */
    private function fakeCGrateSoap(array $responses): void
    {
        Http::fake(function ($request) use ($responses) {
            $body = $request->body();
            foreach ($responses as $operation => $response) {
                if (str_contains($body, $operation)) {
                    return Http::response($response, 200);
                }
            }

            return Http::response($this->soapSuccessBody('queryCustomerPayment', 'Q-1'), 200);
        });
    }

    private function soapSuccessBody(string $operation, string $paymentId): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Body>'
            .'<'.$operation.'Response>'
            .'<return>'
            .'<responseCode>0</responseCode>'
            .'<responseMessage>OK</responseMessage>'
            .'<paymentID>'.$paymentId.'</paymentID>'
            .'</return>'
            .'</'.$operation.'Response>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Admin Co '.$suffix,
            'slug' => 'admin-'.$suffix,
            'code' => 'A'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }
}
