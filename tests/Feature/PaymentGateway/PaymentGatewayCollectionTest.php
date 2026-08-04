<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\CGrate\CGratePaymentGateway;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Services\GatewaySelectionService;
use App\Services\Repayments\RepaymentFinancePostingService;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class PaymentGatewayCollectionTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
    }

    public function test_payment_gateway_tables_migrate_correctly(): void
    {
        $this->assertTrue(Schema::hasTable('payment_gateways'));
        $this->assertTrue(Schema::hasTable('payment_gateway_attempts'));
        $this->assertTrue(Schema::hasTable('payment_gateway_logs'));
        $this->assertTrue(Schema::hasTable('payment_gateway_routes'));
        $this->assertTrue(Schema::hasColumn('repayments', 'payment_gateway_attempt_id'));
    }

    public function test_cgrate_gateway_seeder_creates_inactive_gateway_without_wallet_link(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->first();

        $this->assertNotNull($gateway);
        $this->assertSame(PaymentGatewayStatus::Inactive, $gateway->status);
        $this->assertNull($gateway->financial_account_id);
        $this->assertNull($gateway->financial_account_type);
        $this->assertTrue($gateway->supports_collections);
        $this->assertTrue($gateway->supports_disbursements);
    }

    public function test_gateway_selection_ignores_inactive_gateways(): void
    {
        config(['cgrate.enabled' => true]);

        $channel = $this->makeMobileWalletChannel();
        $service = app(GatewaySelectionService::class);

        $this->assertNull($service->selectForCollection($channel));
    }

    public function test_gateway_selection_picks_active_cgrate_for_mobile_money(): void
    {
        config(['cgrate.enabled' => true]);

        $wallet = Wallet::create([
            'name' => 'cGrate Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);

        $channel = $this->makeMobileWalletChannel();
        $selected = app(GatewaySelectionService::class)->selectForCollection($channel);

        $this->assertNotNull($selected);
        $this->assertSame('cgrate', $selected->code);
    }

    public function test_manual_repayment_approval_still_credits_bank_and_applies_loan(): void
    {
        $context = $this->makeRepaymentContext(integrated: false);
        $bank = Bank::create([
            'name' => 'Test Bank',
            'account_number' => 'ACC-001',
            'account_name' => 'Test',
            'bank_name' => 'Test Bank',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $admin = $this->makeAdmin(['repayments.approve']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.approve', $context['repayment']), [
                'channel_id' => $context['channel']->id,
                'manual_source' => 'bank',
                'bank_id' => $bank->id,
            ])
            ->assertRedirect();

        $bank->refresh();
        $this->assertSame(1300.0, (float) $bank->current_balance);
        $this->assertDatabaseHas('loan_repayments', ['repayment_id' => $context['repayment']->id]);
    }

    public function test_manual_disbursement_still_debits_wallet(): void
    {
        config(['app.disbursement_type' => 'manual']);

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Disb Co '.$suffix,
            'slug' => 'disb-'.$suffix,
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
        $channel = $this->makeMobileWalletChannel($suffix);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'cust-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $loan = Loan::create([
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
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260970000000',
        ]);

        $wallet = Wallet::create([
            'name' => 'Treasury Wallet',
            'wallet_number' => '260955111222',
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);

        $admin = $this->makeAdmin(['loans.disburse']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.disburse', $loan), [
                'source_type' => 'wallet',
                'source_id' => $wallet->id,
                'reference_number' => 'DISB-TEST',
                'disbursement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $wallet->refresh();
        $loan->refresh();
        $this->assertSame(5000.0, (float) $wallet->current_balance);
        $this->assertSame('completed', $loan->disbursement_status);
    }

    public function test_confirmed_gateway_repayment_credits_linked_wallet_and_applies_loan(): void
    {
        $wallet = Wallet::create([
            'name' => 'cGrate Wallet',
            'wallet_number' => '260955999888',
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $context = $this->makeRepaymentContext(integrated: true);
        $attempt = $this->makeConfirmedAttempt($gateway, $context['repayment']);

        app(GatewayIntegrationService::class)->finalizeConfirmedAttempt($attempt);

        $wallet->refresh();
        $context['repayment']->refresh();
        $context['loan']->refresh();

        $this->assertSame(300.0, (float) $wallet->current_balance);
        $this->assertSame('completed', $context['repayment']->status);
        $this->assertSame('wallet', $context['repayment']->received_via_type);
        $this->assertSame($wallet->id, (int) $context['repayment']->received_via_id);
        $this->assertSame(300.0, (float) $context['loan']->amount_paid);
        $this->assertDatabaseHas('loan_repayments', ['repayment_id' => $context['repayment']->id]);
    }

    public function test_gateway_confirmation_is_idempotent(): void
    {
        $wallet = Wallet::create([
            'name' => 'cGrate Wallet',
            'wallet_number' => '260955999777',
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $context = $this->makeRepaymentContext(integrated: true);
        $attempt = $this->makeConfirmedAttempt($gateway, $context['repayment']);
        $service = app(GatewayIntegrationService::class);

        $service->finalizeConfirmedAttempt($attempt);
        $service->finalizeConfirmedAttempt($attempt->fresh());

        $wallet->refresh();
        $this->assertSame(300.0, (float) $wallet->current_balance);
        $this->assertSame(1, $context['repayment']->fresh()->loanRepayments()->count());
    }

    public function test_missing_linked_account_requires_finance_reconciliation(): void
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update(['status' => PaymentGatewayStatus::Active]);

        $context = $this->makeRepaymentContext(integrated: true);
        $attempt = $this->makeConfirmedAttempt($gateway, $context['repayment']);

        app(GatewayIntegrationService::class)->finalizeConfirmedAttempt($attempt);

        $context['repayment']->refresh();
        $this->assertSame('processing', $context['repayment']->status);
        $this->assertTrue($context['repayment']->metadata['requires_finance_reconciliation'] ?? false);
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    public function test_duplicate_callbacks_do_not_double_credit_wallet(): void
    {
        config([
            'cgrate.callback.enabled' => true,
            'cgrate.callback.token' => 'test-token',
        ]);

        $wallet = Wallet::create([
            'name' => 'cGrate Wallet',
            'wallet_number' => '260955999666',
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $context = $this->makeRepaymentContext(integrated: true);
        $attempt = PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Collection,
            'purpose' => \App\PaymentPlatform\Enums\GatewayAttemptPurpose::LoanRepayment,
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $context['repayment']->id,
            'internal_reference' => 'FINEDGE-TEST-REF-001',
            'provider_reference' => 'FINEDGE-TEST-REF-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 300,
            'currency' => 'ZMW',
            'customer_phone' => $context['customer']->phone,
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $context['repayment']->update(['payment_gateway_attempt_id' => $attempt->id]);

        // Simulate already finalized repayment
        app(RepaymentFinancePostingService::class)->creditReceivedAccount(
            $context['repayment']->fresh(),
            'wallet',
            $wallet->id
        );
        app(\App\Services\RepaymentProcessingService::class)->finalizeIntegratedRepayment(
            $context['repayment']->fresh(),
            ['reference' => 'FINEDGE-TEST-REF-001'],
            'Test'
        );

        $walletBalanceBefore = (float) $wallet->fresh()->current_balance;

        $this->postJson(route('webhooks.gateways', ['gatewayCode' => 'cgrate']), [
            'payment_reference' => 'FINEDGE-TEST-REF-001',
            'token' => 'test-token',
        ], ['X-CGrate-Callback-Token' => 'test-token'])->assertOk();

        $this->postJson(route('webhooks.gateways', ['gatewayCode' => 'cgrate']), [
            'payment_reference' => 'FINEDGE-TEST-REF-001',
            'token' => 'test-token',
        ], ['X-CGrate-Callback-Token' => 'test-token'])->assertOk();

        $wallet->refresh();
        $this->assertSame($walletBalanceBefore, (float) $wallet->current_balance);
    }

    public function test_pending_gateway_attempt_does_not_trigger_failover_selection(): void
    {
        config(['cgrate.enabled' => true]);

        $wallet = Wallet::create([
            'name' => 'cGrate Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $cgrate = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $cgrate->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $cgrate->id);

        PaymentGateway::create([
            'name' => 'Backup Gateway',
            'code' => 'backup',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Collection,
            'status' => PaymentGatewayStatus::Active,
            'priority' => 20,
            'supports_collections' => true,
            'supports_mobile_money' => true,
            'supports_callbacks' => true,
            'supports_polling' => true,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $channel = $this->makeMobileWalletChannel();
        $context = $this->makeRepaymentContext(integrated: true);

        PaymentGatewayAttempt::create([
            'payment_gateway_id' => $cgrate->id,
            'direction' => GatewayDirection::Collection,
            'purpose' => \App\PaymentPlatform\Enums\GatewayAttemptPurpose::LoanRepayment,
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $context['repayment']->id,
            'internal_reference' => 'FINEDGE-PENDING-001',
            'provider_reference' => 'FINEDGE-PENDING-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 300,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Pending,
            'initiated_at' => now(),
        ]);

        $selected = app(GatewaySelectionService::class)->selectForCollection($channel);
        $this->assertSame('cgrate', $selected?->code);
    }

    /**
     * @return array{company: Company, channel: Channel, customer: Customer, loan: Loan, repayment: Repayment}
     */
    private function makeRepaymentContext(bool $integrated): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'GW Co '.$suffix,
            'slug' => 'gw-'.$suffix,
            'code' => 'G'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'GW Product',
            'code' => 'GWP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Mobile '.$suffix,
            'code' => 'MOB-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => $integrated,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'GW',
            'last_name' => 'Customer',
            'email' => 'gw-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 1000,
            'outstanding_balance' => 1000,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
        ]);
        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 300,
            'phone_number' => $customer->phone,
            'status' => $integrated ? 'processing' : 'pending',
            'metadata' => ['repayment_type' => 'full'],
        ]);

        return compact('company', 'channel', 'customer', 'loan', 'repayment');
    }

    private function makeMobileWalletChannel(?string $suffix = null): Channel
    {
        $suffix ??= Str::lower(Str::random(6));

        return Channel::create([
            'name' => 'MM '.$suffix,
            'code' => 'MM-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => true,
            'is_active' => true,
        ]);
    }

    private function makeConfirmedAttempt(PaymentGateway $gateway, Repayment $repayment): PaymentGatewayAttempt
    {
        return PaymentGatewayAttempt::create([
            'payment_gateway_id' => $gateway->id,
            'direction' => GatewayDirection::Collection,
            'purpose' => \App\PaymentPlatform\Enums\GatewayAttemptPurpose::LoanRepayment,
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $repayment->id,
            'internal_reference' => 'FINEDGE-'.$repayment->id.'-1-TESTREF01',
            'provider_reference' => 'FINEDGE-'.$repayment->id.'-1-TESTREF01',
            'provider_transaction_id' => 'TXN-123',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => $repayment->total_amount,
            'currency' => 'ZMW',
            'customer_phone' => $repayment->phone_number,
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
            'initiated_at' => now(),
        ]);
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
