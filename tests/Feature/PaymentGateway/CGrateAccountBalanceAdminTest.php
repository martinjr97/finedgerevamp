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
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CGrateAccountBalanceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CGratePaymentGatewaySeeder::class);

        config([
            'cgrate.enabled' => true,
            'cgrate.username' => 'uat-user',
            'cgrate.password' => 'secret-password',
            'cgrate.base_url' => 'https://test.543.cgrate.co.zm',
            'cgrate.default_currency' => 'ZMW',
        ]);
    }

    public function test_show_page_includes_check_cgrate_balance_button_for_cgrate_gateway(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateways.show', $gateway))
            ->assertOk()
            ->assertSee('Check cGrate Balance')
            ->assertSee(route('admin.payment-gateways.cgrate-balance', $gateway), false);
    }

    public function test_check_cgrate_balance_displays_live_float_without_changing_linked_wallet(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $wallet = Wallet::query()->create([
            'name' => 'cGrate Wallet',
            'wallet_number' => 'CGW-'.Str::upper(Str::random(6)),
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

        Http::fake(function ($request) {
            $this->assertStringContainsString('getAccountBalance', $request->body());
            $this->assertStringNotContainsString('processCustomerPayment', $request->body());
            $this->assertStringNotContainsString('processCashDeposit', $request->body());

            return Http::response($this->balanceSoapResponse(0, 'Successful', '98542.68'), 200);
        });

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.cgrate-balance', $gateway));

        $response->assertRedirect(route('admin.payment-gateways.show', $gateway));
        $response->assertSessionHas('cgrate_balance.balance', 98542.68);
        $response->assertSessionMissing('status');

        $this->assertSame(1000.0, (float) $wallet->fresh()->current_balance);
        Http::assertSentCount(1);
    }

    public function test_check_cgrate_balance_requires_permission(): void
    {
        $admin = $this->makeAdmin([]);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.cgrate-balance', $gateway))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_check_cgrate_balance_rejects_non_cgrate_gateway(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);

        $gateway = PaymentGateway::query()->create([
            'name' => 'Other Gateway',
            'code' => 'other',
            'provider_class' => CGratePaymentGateway::class,
            'type' => PaymentGatewayType::Both,
            'status' => PaymentGatewayStatus::Inactive,
            'priority' => 99,
            'is_default' => false,
            'supports_collections' => true,
            'supports_disbursements' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.cgrate-balance', $gateway))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_check_cgrate_balance_surfaces_provider_errors(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        Http::fake([
            '*' => Http::response($this->balanceSoapResponse(2, 'Authentication failed', null), 200),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateways.cgrate-balance', $gateway))
            ->assertRedirect(route('admin.payment-gateways.show', $gateway))
            ->assertSessionHas('cgrate_balance_error', 'Authentication failed');
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

    private function balanceSoapResponse(int $code, string $message, ?string $balance): string
    {
        $balanceXml = $balance !== null ? '<balance>'.$balance.'</balance>' : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Body>'
            .'<ns2:getAccountBalanceResponse xmlns:ns2="http://konik.cgrate.com">'
            .'<return>'
            .'<responseCode>'.$code.'</responseCode>'
            .'<responseMessage>'.htmlspecialchars($message, ENT_XML1).'</responseMessage>'
            .$balanceXml
            .'</return>'
            .'</ns2:getAccountBalanceResponse>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }
}
