<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\CGrate\CGratePaymentGateway;
use App\PaymentPlatform\Services\GatewaySelectionService;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class PaymentGatewayRoutingTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
    }

    public function test_routes_are_seeded_with_unique_keys(): void
    {
        $this->assertSame(5, PaymentGatewayRoute::query()->count());
        $this->assertSame(
            5,
            PaymentGatewayRoute::query()->distinct('route_key')->count('route_key'),
        );
    }

    public function test_disabled_route_returns_null_gateway_for_collection(): void
    {
        config(['cgrate.enabled' => true]);

        $gateway = $this->activateCgrateWithWallet();
        PaymentGatewayRoute::query()
            ->where('route_key', GatewayRouteKey::WalletCollection->value)
            ->update([
                'payment_gateway_id' => $gateway->id,
                'enabled' => false,
            ]);

        $channel = $this->makeMobileWalletChannel();
        $selected = app(GatewaySelectionService::class)->selectForCollection($channel);

        $this->assertNull($selected);
    }

    public function test_enabled_route_returns_configured_gateway_not_priority_scan(): void
    {
        config(['cgrate.enabled' => true]);

        $wallet = $this->makeWallet();
        $cgrate = $this->activateCgrateWithWallet($wallet);

        $backup = PaymentGateway::create([
            'name' => 'Higher Priority Backup',
            'code' => 'backup-routing',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Both,
            'status' => PaymentGatewayStatus::Active,
            'priority' => 1,
            'is_default' => false,
            'supports_collections' => true,
            'supports_disbursements' => true,
            'supports_mobile_money' => true,
            'supports_bank' => true,
            'supports_callbacks' => true,
            'supports_polling' => true,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $cgrate->id);

        $selected = app(GatewaySelectionService::class)->selectForCollection($this->makeMobileWalletChannel());

        $this->assertNotNull($selected);
        $this->assertSame($cgrate->id, $selected->id);
        $this->assertNotSame($backup->id, $selected->id);
    }

    public function test_bank_disbursement_route_excludes_mobile_only_gateway_from_eligibility(): void
    {
        PaymentGateway::create([
            'name' => 'Mobile Only',
            'code' => 'mobile-only',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Disbursement,
            'status' => PaymentGatewayStatus::Active,
            'priority' => 50,
            'supports_collections' => false,
            'supports_disbursements' => true,
            'supports_mobile_money' => true,
            'supports_bank' => false,
            'supports_callbacks' => true,
            'supports_polling' => true,
        ]);

        $eligible = app(PaymentGatewayRouteService::class)
            ->eligibleGateways(GatewayRouteKey::BankDisbursement);

        $this->assertFalse($eligible->contains('code', 'mobile-only'));
        $this->assertTrue($eligible->contains('code', 'cgrate'));
    }

    public function test_inactive_gateway_on_enabled_route_fails_with_manual_fallback(): void
    {
        config(['cgrate.enabled' => true]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Inactive,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $this->makeWallet()->id,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);

        $resolution = app(PaymentGatewayRouteService::class)
            ->resolveRoute(GatewayRouteKey::WalletCollection);

        $this->assertFalse($resolution->available);
        $this->assertTrue($resolution->fallbackToManual);
        $this->assertNotNull($resolution->failureReason);
    }

    public function test_missing_linked_account_fails_resolution(): void
    {
        config(['cgrate.enabled' => true]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => null,
            'financial_account_id' => null,
        ]);

        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);

        $resolution = app(PaymentGatewayRouteService::class)
            ->resolveRoute(GatewayRouteKey::WalletCollection);

        $this->assertFalse($resolution->available);
        $this->assertStringContainsString('linked financial account', strtolower($resolution->failureReason ?? ''));
    }

    public function test_insufficient_balance_allows_disbursement_with_warning(): void
    {
        config(['cgrate.enabled' => true]);

        $gateway = $this->activateCgrateWithWallet($this->makeWallet(100));
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletDisbursement, $gateway->id);

        $loan = $this->makeApprovedWalletLoan(5000);

        $resolution = app(PaymentGatewayRouteService::class)
            ->resolveRoute(GatewayRouteKey::WalletDisbursement, 5000, $loan);

        $this->assertTrue($resolution->available);
        $this->assertTrue($resolution->hasBalanceWarning());
        $this->assertStringContainsString('system balance', strtolower($resolution->balanceWarning ?? ''));
    }

    public function test_cash_channel_has_no_gateway_route(): void
    {
        config(['cgrate.enabled' => true]);
        $this->activateCgrateWithWallet();
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection);

        $channel = Channel::create([
            'name' => 'Cash',
            'code' => 'CASH-'.Str::lower(Str::random(4)),
            'type' => Channel::TYPE_CASH,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $selected = app(GatewaySelectionService::class)->selectForCollection($channel);

        $this->assertNull($selected);
    }

    public function test_cgrate_disabled_in_env_fails_resolution(): void
    {
        config(['cgrate.enabled' => false]);

        $gateway = $this->activateCgrateWithWallet();
        $this->enablePaymentGatewayRoute(GatewayRouteKey::WalletCollection, $gateway->id);

        $resolution = app(PaymentGatewayRouteService::class)
            ->resolveRoute(GatewayRouteKey::WalletCollection);

        $this->assertFalse($resolution->available);
    }

    public function test_admin_save_rejects_ineligible_gateway_for_route(): void
    {
        config(['cgrate.enabled' => true]);

        $mobileOnly = PaymentGateway::create([
            'name' => 'Mobile Only',
            'code' => 'mobile-only-save',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Disbursement,
            'status' => PaymentGatewayStatus::Active,
            'priority' => 50,
            'supports_collections' => false,
            'supports_disbursements' => true,
            'supports_mobile_money' => true,
            'supports_bank' => false,
            'supports_callbacks' => true,
            'supports_polling' => true,
        ]);

        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $route = PaymentGatewayRoute::query()
            ->where('route_key', GatewayRouteKey::BankDisbursement->value)
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.payment-gateway-routing.index'))
            ->put(route('admin.payment-gateway-routing.update', $route), [
                'payment_gateway_id' => $mobileOnly->id,
                'enabled' => '1',
                'auto_process' => '0',
                'fallback_to_manual' => '1',
            ])
            ->assertSessionHasErrors('payment_gateway_id');
    }

    private function activateCgrateWithWallet(?Wallet $wallet = null): PaymentGateway
    {
        $wallet ??= $this->makeWallet();

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        return $gateway->fresh();
    }

    private function makeWallet(float $balance = 10000): Wallet
    {
        return Wallet::create([
            'name' => 'Route Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeMobileWalletChannel(?string $code = null): Channel
    {
        $suffix = Str::lower(Str::random(6));

        return Channel::create([
            'name' => 'MM '.$suffix,
            'code' => $code ?? 'MM-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => true,
            'is_active' => true,
        ]);
    }

    private function makeApprovedWalletLoan(float $amount): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Route Co '.$suffix,
            'slug' => 'route-'.$suffix,
            'code' => 'R'.$suffix,
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
        $channel = $this->makeMobileWalletChannel('MTN_MONEY');
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

        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.$suffix,
            'principal_amount' => $amount,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => $amount,
            'outstanding_balance' => $amount,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'approved',
            'disbursement_status' => 'pending',
            'disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'disbursement_phone_number' => '260970000000',
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
