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
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Jobs\DispatchGatewayDisbursementJob;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\Support\ProcessesQueuedDisbursementJobs;
use Tests\TestCase;

class CGrateDestinationMappingTest extends TestCase
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

    public function test_missing_cgrate_bank_issuer_mapping_blocks_gateway_initiation(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Zambia National Commercial Bank',
            'code' => 'ZANACO',
            'is_active' => true,
        ]);

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);
        $context['loan']->update(['status' => 'approved']);

        Http::fake(function () {
            $this->fail('cGrate SOAP should not be called when bank issuerName mapping is missing.');
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No cGrate issuerName mapping has been configured', $result['message'] ?? '');
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
        $this->assertSame('pending', $context['loan']->fresh()->disbursement_status);
    }

    public function test_active_cgrate_bank_mapping_uses_mapped_issuer_name_not_financial_institution_name(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Zambia National Commercial Bank',
            'code' => 'ZANACO',
            'is_active' => true,
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

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);
        $context['loan']->update(['status' => 'approved']);

        Http::fake(function ($request) use ($institution) {
            $body = $request->body();

            $this->assertStringContainsString('processCashDeposit', $body);
            $this->assertStringContainsString('<issuerName>ZANACO</issuerName>', $body);
            $this->assertStringNotContainsString('<issuerName>'.$institution->name.'</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-BANK-MAP'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->runQueuedDisbursementJob($context['loan']);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'direction' => GatewayDirection::Disbursement->value,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement->value,
            'issuer_name' => 'ZANACO',
        ]);
    }

    public function test_numeric_bank_mapping_value_543_is_sent(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Some Bank',
            'code' => 'SOME',
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        PaymentGatewayDestinationMapping::create([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $institution->id,
            'channel_id' => null,
            'gateway_key' => 'issuerName',
            'gateway_value' => '543',
            'environment' => null,
            'status' => 'active',
        ]);

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);
        $context['loan']->update(['status' => 'approved']);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('<issuerName>543</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-BANK-543'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->runQueuedDisbursementJob($context['loan']);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'direction' => GatewayDirection::Disbursement->value,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement->value,
            'issuer_name' => '543',
        ]);
    }

    public function test_verification_required_bank_mapping_blocks_gateway_initiation(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Zambia National Commercial Bank',
            'code' => 'ZANACO',
            'is_active' => true,
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
            'status' => 'verification_required',
        ]);

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);
        $context['loan']->update(['status' => 'approved']);

        Http::fake(function () {
            $this->fail('cGrate SOAP should not be called when mapping is verification_required.');
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('requires verification before use', $result['message'] ?? '');
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
        $this->assertSame('pending', $context['loan']->fresh()->disbursement_status);
    }

    public function test_environment_specific_mapping_is_used_before_global_mapping(): void
    {
        // Derive environment as "local" (tests are not running under app.env=production).
        config(['cgrate.base_url' => 'https://prod.cgrate.example']);

        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Bank A',
            'code' => 'BANKA',
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        // Global default mapping.
        PaymentGatewayDestinationMapping::create([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $institution->id,
            'channel_id' => null,
            'gateway_key' => 'issuerName',
            'gateway_value' => 'GLOBAL',
            'environment' => null,
            'status' => 'active',
        ]);

        // Exact environment mapping (local) should win.
        PaymentGatewayDestinationMapping::create([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $institution->id,
            'channel_id' => null,
            'gateway_key' => 'issuerName',
            'gateway_value' => 'LOCAL',
            'environment' => 'local',
            'status' => 'active',
        ]);

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);
        $context['loan']->update(['status' => 'approved']);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('<issuerName>LOCAL</issuerName>', $body);
            $this->assertStringNotContainsString('<issuerName>GLOBAL</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-ENV'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->runQueuedDisbursementJob($context['loan']);
    }

    public function test_global_mapping_is_used_when_exact_environment_mapping_is_missing(): void
    {
        // Derive environment as "local".
        config(['cgrate.base_url' => 'https://prod.cgrate.example']);

        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Bank B',
            'code' => 'BANKB',
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        PaymentGatewayDestinationMapping::create([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $institution->id,
            'channel_id' => null,
            'gateway_key' => 'issuerName',
            'gateway_value' => 'GLOBAL_ONLY',
            'environment' => null,
            'status' => 'active',
        ]);

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);
        $context['loan']->update(['status' => 'approved']);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('<issuerName>GLOBAL_ONLY</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-GLOBAL'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->runQueuedDisbursementJob($context['loan']);
    }

    public function test_mobile_money_mapping_override_can_change_issuer_name(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::WalletDisbursement);

        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'AIRTEL_MONEY');
        $context['loan']->update(['status' => 'approved']);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        PaymentGatewayDestinationMapping::create([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'mobile_money',
            'financial_institution_id' => null,
            'channel_id' => $context['channel']->id,
            'gateway_key' => 'issuerName',
            'gateway_value' => '543',
            'environment' => null,
            'status' => 'active',
        ]);

        Http::fake(function ($request) {
            $body = $request->body();
            $this->assertStringContainsString('<issuerName>543</issuerName>', $body);
            $this->assertStringNotContainsString('<issuerName>Airtel</issuerName>', $body);

            return Http::response($this->soapSuccessBody('processCashDeposit', 'DEP-MM-MAP'), 200);
        });

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($context['loan']);

        $this->assertTrue($result['success']);
        $this->runQueuedDisbursementJob($context['loan']);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'issuer_name' => '543',
        ]);
    }

    public function test_admin_can_save_a_cgrate_destination_mapping_for_bank(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $admin = $this->makeAdmin(['payment-gateways.manage']);

        $institution = FinancialInstitution::create([
            'name' => 'Bank UI',
            'code' => 'BANKUI',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.payment-gateway-destination-mappings.store'),
            [
                'payment_gateway_id' => $gateway->id,
                'destination_type' => 'bank',
                'gateway_key' => 'issuerName',
                'gateway_value' => 'BANKUI',
                'environment' => null,
                'status' => 'active',
                'financial_institution_id' => $institution->id,
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('payment_gateway_destination_mappings', [
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $institution->id,
            'gateway_key' => 'issuerName',
            'gateway_value' => 'BANKUI',
            'environment' => null,
            'status' => 'active',
        ]);
    }

    public function test_cgrate_issuer_discovery_includes_numeric_issuer_values(): void
    {
        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'test-user',
            'cgrate.password' => 'test-pass',
            'cgrate.uat.force_disbursement_issuer_name' => false,
            'queue.default' => 'sync',
        ]);

        Http::fake(function () {
            return Http::response($this->issuersSoapResponse(['MTN', '543', 'Airtel']), 200);
        });

        Artisan::call('cgrate:cash-deposit-issuers', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('"543"', $output);
        $this->assertStringContainsString('"MTN"', $output);
    }

    public function test_automatic_disbursement_skips_when_cgrate_bank_mapping_is_missing(): void
    {
        // Required for auto-disbursement.
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Bank Auto',
            'code' => 'BANKAUTO',
            'is_active' => true,
        ]);

        $context = $this->makePendingLoanContext(Channel::TYPE_BANK, 'BANK_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_BANK,
            'disbursement_financial_institution_id' => $institution->id,
            'disbursement_account_number' => '1234567890',
            'disbursement_account_holder_name' => 'Test Holder',
        ]);

        Http::fake(function () {
            $this->fail('cGrate SOAP should not be called when auto-disbursement mapping is missing.');
        });

        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('warning');

        $context['loan']->refresh();
        $this->assertSame('pending', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    /**
     * @param array<string, string>  $responses
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
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Body>'
            . '<'.$operation.'Response>'
            . '<return>'
            . '<responseCode>0</responseCode>'
            . '<responseMessage>OK</responseMessage>'
            . '<paymentID>'.$paymentId.'</paymentID>'
            . '</return>'
            . '</'.$operation.'Response>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    /**
     * @param  list<string>  $issuers
     */
    private function issuersSoapResponse(array $issuers): string
    {
        $items = '';
        foreach ($issuers as $issuer) {
            $items .= '<issuer>'.htmlspecialchars($issuer, ENT_XML1).'</issuer>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Body>'
            . '<getAvailableCashDepositIssuersResponse>'
            . '<return>'.$items.'</return>'
            . '</getAvailableCashDepositIssuersResponse>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    private function activateGatewayWallet(
        float $balance,
        bool $autoProcess = false,
        ?GatewayRouteKey $routeKey = null,
    ): \App\Models\Wallet {
        $wallet = $this->makeTreasuryWallet($balance);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $routeKey ??= GatewayRouteKey::WalletDisbursement;
        $this->enablePaymentGatewayRoute($routeKey, $gateway->id, autoProcess: $autoProcess);

        if ($routeKey !== GatewayRouteKey::BankDisbursement) {
            $this->enablePaymentGatewayRoute(GatewayRouteKey::BankDisbursement, $gateway->id, autoProcess: false);
        }

        if ($routeKey !== GatewayRouteKey::WalletDisbursement) {
            $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id, autoProcess: false);
        }

        return $wallet;
    }

    private function makeTreasuryWallet(float $balance): \App\Models\Wallet
    {
        return \App\Models\Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{company: Company, channel: Channel, customer: Customer, loan: Loan}
     */
    private function makePendingLoanContext(string $channelType, ?string $channelCode = null): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Auto Disb '.$suffix,
            'slug' => 'auto-disb-'.$suffix,
            'code' => 'AD'.$suffix,
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
            'phone' => '260970000000',
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
            'status' => 'pending_approval',
            'disbursement_status' => 'pending',
            'disbursement_channel_type' => $channelType,
        ];

        if ($channelType === Channel::TYPE_MOBILE_WALLET) {
            $loanData['disbursement_phone_number'] = '260970000000';
        }

        $loan = Loan::create($loanData);

        return compact('company', 'channel', 'customer', 'loan');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Admin Co '.$suffix,
            'slug' => 'admin-co-'.$suffix,
            'code' => 'AC'.$suffix,
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
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function approveLoan(Admin $admin, Loan $loan): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin, 'admin')->post(
            route('admin.approvals.loans.approve', $loan),
            [
                'redirect_to_loan' => '1',
                'notes' => 'Approved for auto-disbursement mapping test',
            ]
        );
    }
}

