<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
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
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\Services\Repayments\AdminRepaymentGatewayCollectionService;
use App\Support\Queue\FinancialQueue;
use App\Support\RepaymentRecoveryMethod;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\PaymentPlatform\Jobs\DispatchGatewayCollectionJob;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class AdminRepaymentGatewayCollectionTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
    }

    public function test_manual_cash_repayment_creation_remains_pending_without_gateway_attempt(): void
    {
        $context = $this->makeAdminRepaymentContext(channelType: Channel::TYPE_CASH);
        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $response->assertSessionHas('status');
        $this->assertSame('pending', $repayment->status);
        $this->assertSame('manual', $repayment->metadata['submission_mode'] ?? null);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_wallet_collection_route_ready_creates_processing_repayment_and_gateway_attempt(): void
    {
        $context = $this->makeAdminRepaymentContext();
        $this->activateWalletCollectionRoute();
        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $response->assertSessionHas('status');
        $this->assertSame('processing', $repayment->status);
        $this->assertSame('gateway_collection', $repayment->metadata['submission_mode'] ?? null);
        $this->assertDatabaseHas('payment_gateway_attempts', [
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $repayment->id,
            'direction' => GatewayDirection::Collection->value,
        ]);
    }

    public function test_dispatch_gateway_collection_job_is_queued_on_payments_high(): void
    {
        config(['queue.default' => 'redis']);
        Queue::fake();

        $context = $this->makeAdminRepaymentContext();
        $this->activateWalletCollectionRoute();
        $admin = $this->makeAdmin(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            ]);

        Queue::assertPushed(DispatchGatewayCollectionJob::class, function (DispatchGatewayCollectionJob $job) {
            return $job->queue === FinancialQueue::paymentsHigh()
                && $job->connection === FinancialQueue::connection();
        });
    }

    public function test_loan_balance_is_unchanged_before_gateway_confirmation(): void
    {
        $context = $this->makeAdminRepaymentContext();
        $this->activateWalletCollectionRoute();
        $admin = $this->makeAdmin(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            ]);

        $context['loan']->refresh();
        $this->assertSame(0.0, (float) $context['loan']->amount_paid);
        $this->assertSame(1000.0, (float) $context['loan']->outstanding_balance);
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    public function test_linked_gateway_account_is_not_credited_before_confirmation(): void
    {
        $context = $this->makeAdminRepaymentContext();
        $wallet = $this->activateWalletCollectionRoute();
        $admin = $this->makeAdmin(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            ]);

        $wallet->refresh();
        $this->assertSame(0.0, (float) $wallet->current_balance);
    }

    public function test_gateway_confirmation_finalizes_repayment_and_updates_finance(): void
    {
        $context = $this->makeAdminRepaymentContext();
        $wallet = $this->activateWalletCollectionRoute();
        $admin = $this->makeAdmin(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $attempt = PaymentGatewayAttempt::query()
            ->where('attemptable_id', $repayment->id)
            ->where('attemptable_type', Repayment::class)
            ->firstOrFail();

        $attempt->update([
            'status' => GatewayAttemptStatus::Confirmed,
            'confirmed_at' => now(),
            'provider_transaction_id' => 'TXN-CONFIRMED',
        ]);

        app(GatewayIntegrationService::class)->finalizeConfirmedAttempt($attempt->fresh());

        $wallet->refresh();
        $repayment->refresh();
        $context['loan']->refresh();

        $this->assertSame('completed', $repayment->status);
        $this->assertSame(1000.0, (float) $wallet->current_balance);
        $this->assertSame(1000.0, (float) $context['loan']->amount_paid);
        $this->assertDatabaseHas('loan_repayments', ['repayment_id' => $repayment->id]);
    }

    public function test_disabled_route_with_fallback_creates_pending_manual_repayment_with_warning(): void
    {
        $context = $this->makeAdminRepaymentContext();
        $this->activateWalletCollectionRoute(enabled: false);
        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $response->assertSessionHas('warning');
        $this->assertSame('pending', $repayment->status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_missing_linked_account_with_fallback_creates_pending_manual_repayment_with_warning(): void
    {
        $context = $this->makeAdminRepaymentContext();
        config(['cgrate.enabled' => true]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update(['status' => PaymentGatewayStatus::Active]);
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);

        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'phone_number' => $context['customer']->phone,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $response->assertSessionHas('warning');
        $this->assertSame('pending', $repayment->status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_missing_customer_phone_falls_back_to_manual_when_fallback_is_enabled(): void
    {
        $context = $this->makeAdminRepaymentContext(clearCustomerPhone: true);
        $this->activateWalletCollectionRoute();
        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $response->assertSessionHas('warning');
        $this->assertSame('pending', $repayment->status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    public function test_missing_customer_phone_returns_validation_error_when_fallback_is_disabled(): void
    {
        $context = $this->makeAdminRepaymentContext(clearCustomerPhone: true);
        $this->activateWalletCollectionRoute(fallbackToManual: false);
        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            ]);

        $response->assertSessionHasErrors('channel_id');
        $this->assertDatabaseCount('repayments', 0);
    }

    public function test_duplicate_active_collection_attempt_is_blocked(): void
    {
        $context = $this->makeAdminRepaymentContext();
        $this->activateWalletCollectionRoute();

        $repayment = Repayment::create([
            'customer_id' => $context['customer']->id,
            'channel_id' => $context['channel']->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 300,
            'phone_number' => $context['customer']->phone,
            'status' => 'processing',
            'metadata' => ['repayment_type' => 'full'],
        ]);

        PaymentGatewayAttempt::create([
            'payment_gateway_id' => PaymentGateway::query()->where('code', 'cgrate')->value('id'),
            'direction' => GatewayDirection::Collection,
            'purpose' => \App\PaymentPlatform\Enums\GatewayAttemptPurpose::LoanRepayment,
            'attemptable_type' => Repayment::class,
            'attemptable_id' => $repayment->id,
            'internal_reference' => 'FINEDGE-DUP-001',
            'provider_reference' => 'FINEDGE-DUP-001',
            'payment_method' => GatewayPaymentMethod::MobileMoney,
            'amount' => 300,
            'currency' => 'ZMW',
            'status' => GatewayAttemptStatus::Pending,
            'initiated_at' => now(),
        ]);

        $result = app(AdminRepaymentGatewayCollectionService::class)->initiateForRepayment(
            $repayment,
            $context['channel'],
            $context['customer']->phone,
        );

        $this->assertTrue($result->status->usesWarningFlash());
        $this->assertStringContainsString('active gateway collection attempt', strtolower($result->message));
        $this->assertDatabaseCount('payment_gateway_attempts', 1);
    }

    public function test_bank_collection_route_with_unsupported_provider_falls_back_to_manual(): void
    {
        $context = $this->makeAdminRepaymentContext(channelType: Channel::TYPE_BANK);
        $this->activateWalletCollectionRoute();
        $gatewayId = PaymentGateway::query()->where('code', 'cgrate')->value('id');
        $this->enablePaymentGatewayRoute(GatewayRouteKey::BankCollection, $gatewayId);
        $admin = $this->makeAdmin(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $context['customer']), [
                'repayment_type' => 'full',
                'channel_id' => $context['channel']->id,
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'bank',
                'bank_id' => $context['bank']->id,
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $response->assertSessionHas('warning');
        $this->assertSame('pending', $repayment->status);
        $this->assertDatabaseCount('payment_gateway_attempts', 0);
    }

    /**
     * @return array{company: Company, channel: Channel, customer: Customer, loan: Loan, bank: \App\Models\Bank}
     */
    private function makeAdminRepaymentContext(
        ?string $channelType = Channel::TYPE_MOBILE_WALLET,
        ?string $customerPhone = '260970000000',
        bool $clearCustomerPhone = false,
    ): array {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Admin Repay Co '.$suffix,
            'slug' => 'admin-repay-'.$suffix,
            'code' => 'AR'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Admin Repay Product',
            'code' => 'ARP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'type' => $channelType,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => $channelType === Channel::TYPE_MOBILE_WALLET,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Admin',
            'last_name' => 'Borrower',
            'email' => 'admin-borrower-'.$suffix.'@example.com',
            'phone' => $customerPhone,
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        if ($clearCustomerPhone) {
            $customer->update(['phone' => null]);
            $customer->refresh();
        }
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
        $bank = \App\Models\Bank::create([
            'name' => 'Bank '.$suffix,
            'account_number' => 'BNK-'.$suffix,
            'account_name' => 'Test',
            'bank_name' => 'Test Bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        return compact('company', 'channel', 'customer', 'loan', 'bank');
    }

    private function activateWalletCollectionRoute(
        bool $enabled = true,
        bool $fallbackToManual = true,
    ): Wallet {
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

        $route = $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);
        $route->update([
            'enabled' => $enabled,
            'fallback_to_manual' => $fallbackToManual,
        ]);

        return $wallet;
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
