<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\PaymentPlatform\Enums\PaymentGatewayType;
use App\PaymentPlatform\Providers\CGrate\CGratePaymentGateway;
use App\PaymentPlatform\Providers\Kazang\KazangApiClient;
use App\PaymentPlatform\Providers\Kazang\KazangException;
use Database\Seeders\KazangPaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KazangAccountBalanceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(KazangPaymentGatewaySeeder::class);

        config([
            'kazang.enabled' => true,
            'kazang.default_currency' => 'ZMW',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_show_page_includes_check_kazang_balance_button_for_kazang_gateway(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'kazang')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateways.show', $gateway))
            ->assertOk()
            ->assertSee('Check Kazang Balance')
            ->assertSee(route('admin.payment-gateways.kazang-balance', $gateway), false);
    }

    public function test_check_kazang_balance_displays_live_float_without_changing_linked_wallet(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'kazang')->firstOrFail();

        $wallet = Wallet::query()->create([
            'name' => 'Kazang Wallet',
            'wallet_number' => 'KZW-'.Str::upper(Str::random(6)),
            'provider' => 'other',
            'currency' => 'ZMW',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $gateway->update([
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
            'status' => PaymentGatewayStatus::Active,
        ]);

        $mock = Mockery::mock(KazangApiClient::class);
        $mock->shouldReceive('getMerchantBalance')
            ->once()
            ->andReturn([
                'balance' => 45230.50,
                'currency' => 'ZMW',
                'response_code' => '0',
                'response_message' => 'Successful',
                'checked_at' => now()->toIso8601String(),
                'raw' => ['kazang' => ['response_code' => '0', 'balance' => 45230.50]],
            ]);
        $this->app->instance(KazangApiClient::class, $mock);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.kazang-balance', $gateway));

        $response->assertRedirect(route('admin.payment-gateways.show', $gateway));
        $response->assertSessionHas('kazang_balance.balance', 45230.50);
        $response->assertSessionMissing('status');

        $this->assertSame(1000.0, (float) $wallet->fresh()->current_balance);
    }

    public function test_check_kazang_balance_requires_permission(): void
    {
        $admin = $this->makeAdmin([]);
        $gateway = PaymentGateway::query()->where('code', 'kazang')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.kazang-balance', $gateway))
            ->assertForbidden();
    }

    public function test_check_kazang_balance_rejects_non_kazang_gateway(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);

        $gateway = PaymentGateway::query()->create([
            'name' => 'Other Gateway',
            'code' => 'other-kazang-test',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Both,
            'status' => PaymentGatewayStatus::Inactive,
            'priority' => 99,
            'is_default' => false,
            'supports_collections' => true,
            'supports_disbursements' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.kazang-balance', $gateway))
            ->assertNotFound();
    }

    public function test_check_kazang_balance_surfaces_provider_errors(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'kazang')->firstOrFail();

        $mock = Mockery::mock(KazangApiClient::class);
        $mock->shouldReceive('getMerchantBalance')
            ->once()
            ->andThrow(new KazangException('Authentication failed'));
        $this->app->instance(KazangApiClient::class, $mock);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.kazang-balance', $gateway))
            ->assertRedirect(route('admin.payment-gateways.show', $gateway))
            ->assertSessionHas('kazang_balance_error', 'Authentication failed');
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
}
