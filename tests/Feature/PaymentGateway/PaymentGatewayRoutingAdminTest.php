<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\CGrate\CGratePaymentGateway;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnablesPaymentGatewayRoutes;
use Tests\TestCase;

class PaymentGatewayRoutingAdminTest extends TestCase
{
    use EnablesPaymentGatewayRoutes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CGratePaymentGatewaySeeder::class);
        $this->seedPaymentGatewayRoutes();
    }

    public function test_routing_page_shows_four_table_rows(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-routing.index'));

        $response->assertOk()
            ->assertSeeText('Wallet & Mobile Money Collections')
            ->assertSeeText('Bank Collections')
            ->assertSeeText('Wallet & Mobile Money Disbursements')
            ->assertSeeText('Bank Account Disbursements')
            ->assertDontSee('Card Collections');
    }

    public function test_wallet_disbursement_route_persists_after_save_and_reload(): void
    {
        config(['cgrate.enabled' => true]);

        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $gateway = $this->prepareGateway();
        $route = $this->routeFor(GatewayRouteKey::WalletDisbursement);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.payment-gateway-routing.update', $route), [
                'payment_gateway_id' => $gateway->id,
                'enabled' => '1',
                'auto_process' => '1',
                'fallback_to_manual' => '1',
                'notes' => 'Primary wallet disbursement route',
            ])
            ->assertRedirect(route('admin.payment-gateway-routing.index'))
            ->assertSessionHas('status');

        $route->refresh();
        $this->assertSame($gateway->id, $route->payment_gateway_id);
        $this->assertTrue($route->enabled);
        $this->assertTrue($route->auto_process);
        $this->assertTrue($route->fallback_to_manual);
        $this->assertSame('Primary wallet disbursement route', $route->notes);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-routing.index'))
            ->assertOk()
            ->assertSee('cGrate', false)
            ->assertSee('Yes', false)
            ->assertSee('Primary wallet disbursement route', false);
    }

    public function test_unchecked_toggles_persist_false(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $gateway = $this->prepareGateway();
        $route = $this->routeFor(GatewayRouteKey::WalletCollection);

        $route->update([
            'payment_gateway_id' => $gateway->id,
            'enabled' => true,
            'auto_process' => true,
            'fallback_to_manual' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.payment-gateway-routing.update', $route), [
                'payment_gateway_id' => $gateway->id,
                'enabled' => '0',
                'auto_process' => '0',
                'fallback_to_manual' => '0',
            ])
            ->assertRedirect(route('admin.payment-gateway-routing.index'));

        $route->refresh();
        $this->assertFalse($route->enabled);
        $this->assertFalse($route->auto_process);
        $this->assertFalse($route->fallback_to_manual);
    }

    public function test_disabled_route_can_save_without_gateway(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $route = $this->routeFor(GatewayRouteKey::BankCollection);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.payment-gateway-routing.update', $route), [
                'payment_gateway_id' => '',
                'enabled' => '0',
                'auto_process' => '0',
                'fallback_to_manual' => '1',
            ])
            ->assertRedirect(route('admin.payment-gateway-routing.index'));

        $route->refresh();
        $this->assertNull($route->payment_gateway_id);
        $this->assertFalse($route->enabled);
    }

    public function test_enabled_route_without_gateway_fails_validation(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $route = $this->routeFor(GatewayRouteKey::WalletCollection);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.payment-gateway-routing.index'))
            ->put(route('admin.payment-gateway-routing.update', $route), [
                'payment_gateway_id' => '',
                'enabled' => '1',
                'auto_process' => '0',
                'fallback_to_manual' => '1',
            ])
            ->assertRedirect(route('admin.payment-gateway-routing.index'))
            ->assertSessionHasErrors('payment_gateway_id');
    }

    public function test_ineligible_gateway_is_rejected_for_route(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $route = $this->routeFor(GatewayRouteKey::BankDisbursement);

        $mobileOnly = PaymentGateway::create([
            'name' => 'Mobile Only',
            'code' => 'mobile-only-admin',
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

    public function test_manage_permission_required_to_update_route(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $route = $this->routeFor(GatewayRouteKey::WalletCollection);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.payment-gateway-routing.update', $route), [
                'payment_gateway_id' => '',
                'enabled' => '0',
                'fallback_to_manual' => '1',
            ])
            ->assertForbidden();
    }

    public function test_status_badges_reflect_saved_state(): void
    {
        config(['cgrate.enabled' => true]);

        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = $this->prepareGateway();

        $this->routeFor(GatewayRouteKey::WalletDisbursement)->update([
            'payment_gateway_id' => $gateway->id,
            'enabled' => true,
            'auto_process' => true,
            'fallback_to_manual' => true,
        ]);

        $this->routeFor(GatewayRouteKey::BankCollection)->update([
            'payment_gateway_id' => null,
            'enabled' => false,
        ]);

        $unlinkedGateway = PaymentGateway::create([
            'name' => 'Unlinked Gateway',
            'code' => 'unlinked-gw',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Both,
            'status' => PaymentGatewayStatus::Active,
            'priority' => 99,
            'supports_collections' => true,
            'supports_disbursements' => true,
            'supports_mobile_money' => true,
            'supports_bank' => true,
            'supports_callbacks' => true,
            'supports_polling' => true,
        ]);

        $this->routeFor(GatewayRouteKey::WalletCollection)->update([
            'payment_gateway_id' => $unlinkedGateway->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-routing.index'));

        $response->assertOk()
            ->assertSee('Ready', false)
            ->assertSee('Disabled', false)
            ->assertSee('Missing Linked Account', false);
    }

    public function test_payment_gateways_index_links_to_routing_page(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateways.index'))
            ->assertOk()
            ->assertSee('Gateway Routing →', false);
    }

    private function routeFor(GatewayRouteKey $routeKey): PaymentGatewayRoute
    {
        return PaymentGatewayRoute::query()
            ->where('route_key', $routeKey->value)
            ->firstOrFail();
    }

    private function prepareGateway(): PaymentGateway
    {
        $wallet = Wallet::create([
            'name' => 'Admin Route Wallet',
            'wallet_number' => '260955'.random_int(100000, 999999),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        return $gateway->fresh();
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
