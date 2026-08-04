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
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\Support\ProcessesQueuedDisbursementJobs;
use Tests\TestCase;

class AdminLoanApprovalGatewayAutoDisbursementTest extends TestCase
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
            'queue.default' => 'sync',
        ]);
    }

    public function test_approval_with_no_route_enabled_leaves_loan_pending_without_attempt(): void
    {
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertRedirect(route('admin.loans.show', $context['loan']))
            ->assertSessionHas('status');

        $context['loan']->refresh();
        $this->assertSame('approved', $context['loan']->status);
        $this->assertSame('pending', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_approval_with_wallet_route_enabled_but_auto_process_off_skips_attempt(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id, autoProcess: false);

        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('status', 'Loan approved successfully. Please complete manual disbursement.');

        $context['loan']->refresh();
        $this->assertSame('approved', $context['loan']->status);
        $this->assertSame('pending', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_approval_with_wallet_auto_process_initiates_gateway_disbursement(): void
    {
        $wallet = $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::WalletDisbursement);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);

        $this->fakeCGrateSoap([
            'processCashDeposit' => $this->soapSuccessBody('processCashDeposit', 'DEP-AUTO-001'),
        ]);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('status', 'Loan approved successfully. Gateway disbursement has been initiated.');

        $this->runQueuedDisbursementJob($context['loan']);

        $context['loan']->refresh();
        $this->assertSame('active', $context['loan']->status);
        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'direction' => GatewayDirection::Disbursement->value,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement->value,
            'status' => GatewayAttemptStatus::Confirmed->value,
        ]);
        $this->assertSame(15000.0, (float) $wallet->fresh()->current_balance);
    }

    public function test_approval_with_bank_auto_process_initiates_gateway_disbursement(): void
    {
        $wallet = $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::BankDisbursement);

        $institution = FinancialInstitution::create([
            'name' => 'Zanaco',
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

        $admin = $this->makeAdmin(['loans.approve']);

        $this->fakeCGrateSoap([
            'processCashDeposit' => $this->soapSuccessBody('processCashDeposit', 'DEP-BANK-AUTO'),
        ]);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('status', 'Loan approved successfully. Gateway disbursement has been initiated.');

        $this->runQueuedDisbursementJob($context['loan']);

        $context['loan']->refresh();
        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_id' => $context['loan']->id,
            'direction' => GatewayDirection::Disbursement->value,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement->value,
            'status' => GatewayAttemptStatus::Confirmed->value,
        ]);
        $this->assertSame(15000.0, (float) $wallet->fresh()->current_balance);
    }

    public function test_approval_with_auto_route_but_inactive_gateway_skips_with_warning(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id, autoProcess: true);

        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('warning');

        $context['loan']->refresh();
        $this->assertSame('approved', $context['loan']->status);
        $this->assertSame('pending', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_approval_with_auto_route_but_missing_linked_account_skips_with_warning(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update(['status' => PaymentGatewayStatus::Active]);
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id, autoProcess: true);

        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('warning');

        $context['loan']->refresh();
        $this->assertSame('approved', $context['loan']->status);
        $this->assertSame('pending', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_approval_with_auto_route_but_insufficient_balance_still_completes_gateway_disbursement(): void
    {
        $wallet = $this->activateGatewayWallet(1000, autoProcess: true, routeKey: GatewayRouteKey::WalletDisbursement);

        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);

        $this->fakeCGrateSoap([
            'processCashDeposit' => $this->soapSuccessBody('processCashDeposit', 'DEP-LOW-BAL'),
        ]);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('status', 'Loan approved successfully. Gateway disbursement has been initiated.');

        $this->runQueuedDisbursementJob($context['loan']);

        $context['loan']->refresh();
        $this->assertSame('active', $context['loan']->status);
        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 1);
        $this->assertSame(-4000.0, (float) $wallet->fresh()->current_balance);
    }

    public function test_approval_with_cash_destination_skips_auto_disbursement(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::WalletDisbursement);

        $context = $this->makePendingLoanContext(Channel::TYPE_CASH, 'CASH_CH');
        $context['loan']->update([
            'disbursement_channel_type' => Channel::TYPE_CASH,
        ]);

        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('warning');

        $context['loan']->refresh();
        $this->assertSame('approved', $context['loan']->status);
        $this->assertSame('pending', $context['loan']->disbursement_status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_approval_with_existing_pending_attempt_does_not_create_duplicate(): void
    {
        $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::WalletDisbursement);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Disbursement,
            'purpose' => GatewayAttemptPurpose::LoanDisbursement,
            'attemptable_type' => Loan::class,
            'attemptable_id' => $context['loan']->id,
            'internal_reference' => 'FINEDGE-OUT-EXISTING-001',
            'provider_reference' => 'FINEDGE-OUT-EXISTING-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 5000,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Pending,
            'initiated_at' => now(),
        ]);

        $admin = $this->makeAdmin(['loans.approve']);

        $this->approveLoan($admin, $context['loan'])
            ->assertSessionHas('warning');

        $this->assertSame(1, PaymentGatewayAttempt::query()
            ->where('attemptable_id', $context['loan']->id)
            ->count());
    }

    public function test_confirmed_payout_remains_idempotent_after_auto_approval(): void
    {
        $wallet = $this->activateGatewayWallet(20000, autoProcess: true, routeKey: GatewayRouteKey::WalletDisbursement);
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $admin = $this->makeAdmin(['loans.approve']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $this->fakeCGrateSoap([
            'processCashDeposit' => $this->soapSuccessBody('processCashDeposit', 'DEP-IDEM-AUTO'),
        ]);

        $this->approveLoan($admin, $context['loan']);

        $attempt = PaymentGatewayAttempt::query()
            ->where('attemptable_id', $context['loan']->id)
            ->firstOrFail();

        $attempt->update([
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $service = app(GatewayIntegrationService::class);
        $service->finalizeConfirmedDisbursement($attempt);
        $service->finalizeConfirmedDisbursement($attempt->fresh());

        $wallet->refresh();
        $this->assertSame(15000.0, (float) $wallet->current_balance);
        $this->assertSame('completed', $context['loan']->fresh()->disbursement_status);
    }

    public function test_manual_disbursement_still_works_after_skipped_auto(): void
    {
        $context = $this->makePendingLoanContext(Channel::TYPE_MOBILE_WALLET, 'MTN_MONEY');
        $wallet = $this->makeTreasuryWallet(10000);
        $approver = $this->makeAdmin(['loans.approve']);
        $disburser = $this->makeAdmin(['loans.disburse']);

        $this->approveLoan($approver, $context['loan'])
            ->assertSessionHas('status', 'Loan approved successfully. Please complete manual disbursement.');

        $this->actingAs($disburser, 'admin')
            ->post(route('admin.loans.disburse', $context['loan']), [
                'source_type' => 'wallet',
                'source_id' => $wallet->id,
                'reference_number' => 'MANUAL-AFTER-SKIP',
                'disbursement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $context['loan']->refresh();
        $this->assertSame('completed', $context['loan']->disbursement_status);
        $this->assertSame(5000.0, (float) $wallet->fresh()->current_balance);
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

    private function activateGatewayWallet(
        float $balance,
        bool $autoProcess = false,
        ?GatewayRouteKey $routeKey = null,
    ): Wallet {
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
                'notes' => 'Approved for auto-disbursement test',
            ]
        );
    }
}
