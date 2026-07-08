<?php

namespace Tests\Feature\PaymentGateway;

use App\Models\Admin;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayDestinationMapping;
use App\Support\CGrateIssuerDiscoveryCache;
use Database\Seeders\CGratePaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentGatewayDestinationMappingAdminTest extends TestCase
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
        ]);
    }

    public function test_destination_mapping_page_loads_for_viewers(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-destination-mappings.index'))
            ->assertOk()
            ->assertSee('Payment Gateway Destination Mappings')
            ->assertSee('Bank Mapping Coverage')
            ->assertSee('Latest cGrate Issuers');
    }

    public function test_viewer_cannot_create_update_or_delete_mappings(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $bank = $this->makeBank('ZANACO');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateway-destination-mappings.store'), $this->payload($gateway, $bank))
            ->assertForbidden();

        $mapping = PaymentGatewayDestinationMapping::create($this->mappingAttributes($gateway, $bank));

        $this->actingAs($admin, 'admin')
            ->put(route('admin.payment-gateway-destination-mappings.update', $mapping), $this->payload($gateway, $bank))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.payment-gateway-destination-mappings.destroy', $mapping))
            ->assertForbidden();
    }

    public function test_manager_can_create_edit_and_delete_mapping(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $bank = $this->makeBank('ZANACO');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateway-destination-mappings.store'), $this->payload($gateway, $bank, [
                'gateway_value' => '543',
                'environment' => 'uat',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $mapping = PaymentGatewayDestinationMapping::query()->firstOrFail();
        $this->assertSame('543', $mapping->gateway_value);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.payment-gateway-destination-mappings.update', $mapping), $this->payload($gateway, $bank, [
                'gateway_value' => '544',
                'status' => 'verification_required',
                'notes' => 'Needs confirmation',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $mapping->refresh();
        $this->assertSame('544', $mapping->gateway_value);
        $this->assertSame('verification_required', $mapping->status);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.payment-gateway-destination-mappings.destroy', $mapping))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('payment_gateway_destination_mappings', ['id' => $mapping->id]);
    }

    public function test_duplicate_active_mapping_validation_is_friendly(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $bank = $this->makeBank('ABSA');

        PaymentGatewayDestinationMapping::create($this->mappingAttributes($gateway, $bank, [
            'gateway_value' => '543',
            'environment' => 'uat',
            'status' => 'active',
        ]));

        $this->actingAs($admin, 'admin')
            ->from(route('admin.payment-gateway-destination-mappings.index'))
            ->post(route('admin.payment-gateway-destination-mappings.store'), $this->payload($gateway, $bank, [
                'gateway_value' => '999',
                'environment' => 'uat',
                'status' => 'active',
            ]))
            ->assertRedirect(route('admin.payment-gateway-destination-mappings.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_filters_search_bank_name_and_gateway_value(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $bank = $this->makeBank('STANBIC');

        PaymentGatewayDestinationMapping::create($this->mappingAttributes($gateway, $bank, [
            'gateway_value' => '777',
        ]));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-destination-mappings.index', [
                'gateway_id' => $gateway->id,
                'search' => 'STANBIC',
            ]))
            ->assertOk()
            ->assertSee('STANBIC')
            ->assertSee('777');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-destination-mappings.index', [
                'search' => '777',
            ]))
            ->assertOk()
            ->assertSee('777');
    }

    public function test_coverage_table_shows_missing_bank_mapping(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $this->makeBank('UNMAPPED_BANK');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-destination-mappings.index'))
            ->assertOk()
            ->assertSee('UNMAPPED_BANK')
            ->assertSee('Missing Mapping');
    }

    public function test_sync_cgrate_issuers_stores_numeric_values_in_cache(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        Http::fake(function () {
            return Http::response($this->issuersSoapResponse(['543', 'ABC Bank']), 200);
        });

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateway-destination-mappings.sync-cgrate-issuers'))
            ->assertRedirectToRoute('admin.payment-gateway-destination-mappings.index', ['gateway_id' => $gateway->id])
            ->assertSessionHas('status');

        $cached = app(CGrateIssuerDiscoveryCache::class)->latest();
        $this->assertNotNull($cached);
        $this->assertContains('543', $cached['issuers']);
        $this->assertContains('ABC Bank', $cached['issuers']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateway-destination-mappings.index'))
            ->assertOk()
            ->assertSee('543')
            ->assertSee('Numeric')
            ->assertSee('ABC Bank');
    }

    public function test_mapping_crud_writes_audit_log_entries(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.manage']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $bank = $this->makeBank('FNB');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.payment-gateway-destination-mappings.store'), $this->payload($gateway, $bank))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'pg_dest_mapping.created',
            'actor_id' => (string) $admin->id,
        ]);

        $mapping = PaymentGatewayDestinationMapping::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.payment-gateway-destination-mappings.destroy', $mapping))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'pg_dest_mapping.deleted',
            'actor_id' => (string) $admin->id,
        ]);
    }

    public function test_legacy_nested_route_redirects_to_standalone_page(): void
    {
        $admin = $this->makeAdmin(['payment-gateways.view']);
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payment-gateways.destination-mappings.index', $gateway))
            ->assertRedirect(route('admin.payment-gateway-destination-mappings.index', [
                'gateway_id' => $gateway->id,
            ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(PaymentGateway $gateway, FinancialInstitution $bank, array $overrides = []): array
    {
        return array_merge([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $bank->id,
            'gateway_key' => 'issuerName',
            'gateway_value' => '543',
            'environment' => null,
            'status' => 'active',
            'notes' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mappingAttributes(PaymentGateway $gateway, FinancialInstitution $bank, array $overrides = []): array
    {
        return array_merge([
            'payment_gateway_id' => $gateway->id,
            'destination_type' => 'bank',
            'financial_institution_id' => $bank->id,
            'channel_id' => null,
            'gateway_key' => 'issuerName',
            'gateway_value' => '543',
            'environment' => null,
            'status' => 'active',
        ], $overrides);
    }

    private function makeBank(string $code): FinancialInstitution
    {
        return FinancialInstitution::create([
            'name' => $code.' Bank',
            'code' => $code,
            'is_active' => true,
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
}
