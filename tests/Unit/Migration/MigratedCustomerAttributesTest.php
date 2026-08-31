<?php

namespace Tests\Unit\Migration;

use App\Migration\Phases\Support\MigratedCustomerAttributes;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigratedCustomerAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_registration_timestamps_prefers_legacy_customer_created_at(): void
    {
        $timestamps = MigratedCustomerAttributes::resolveRegistrationTimestamps(
            ['created_at' => '2020-01-01 10:00:00', 'updated_at' => '2020-06-01 10:00:00'],
            ['created_at' => '2021-04-08 16:41:01', 'updated_at' => '2022-01-01 08:00:00'],
        );

        $this->assertNotNull($timestamps);
        $this->assertSame('2021-04-08 16:41:01', $timestamps['created_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2022-01-01 08:00:00', $timestamps['updated_at']->format('Y-m-d H:i:s'));
    }

    public function test_apply_registration_timestamps_updates_customer_created_at(): void
    {
        $company = Company::create([
            'name' => 'Legacy Date Co',
            'slug' => 'legacy-date-co',
            'code' => 'LDC',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Legacy Product',
            'code' => 'LEG-'.Str::lower(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Legacy',
            'last_name' => 'Customer',
            'email' => 'legacy-date-'.Str::random(5).'@example.com',
            'phone' => '260977000099',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'metadata' => ['source_system' => 'finedge_legacy', 'legacy_user_id' => 10],
        ]);

        $this->assertTrue(Carbon::parse($customer->created_at)->isToday());

        $applied = MigratedCustomerAttributes::applyRegistrationTimestamps(
            $customer,
            ['created_at' => '2021-04-08 16:41:01', 'updated_at' => '2021-05-01 09:00:00'],
            ['created_at' => '2021-04-08 16:41:01'],
            true,
        );

        $this->assertTrue($applied);
        $customer->refresh();
        $this->assertSame('2021-04-08 16:41:01', $customer->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2021-04-08 16:41:01',
            Carbon::parse($customer->metadata['legacy_registered_at'])->format('Y-m-d H:i:s'),
        );
    }

    public function test_resolve_legacy_password_hash_accepts_bcrypt_only(): void
    {
        $hash = Hash::make('1234');

        $this->assertSame($hash, MigratedCustomerAttributes::resolveLegacyPasswordHash(['password' => $hash]));
        $this->assertNull(MigratedCustomerAttributes::resolveLegacyPasswordHash(['password' => '1234']));
        $this->assertNull(MigratedCustomerAttributes::resolveLegacyPasswordHash(['password' => '']));
    }

    public function test_apply_legacy_password_preserves_hash_for_pin_login(): void
    {
        $company = Company::create([
            'name' => 'Legacy Pin Co',
            'slug' => 'legacy-pin-co',
            'code' => 'LPC',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Pin Product',
            'code' => 'PIN-'.Str::lower(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $legacyHash = Hash::make('4321');

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Pin',
            'last_name' => 'User',
            'email' => 'legacy-pin-'.Str::random(5).'@example.com',
            'phone' => '260977000088',
            'password' => bcrypt('temporary'),
            'status' => 'active',
            'approval_status' => 'approved',
            'metadata' => ['source_system' => 'finedge_legacy'],
        ]);

        $this->assertFalse(Hash::check('4321', $customer->password));

        $applied = MigratedCustomerAttributes::applyLegacyPassword(
            $customer,
            ['password' => $legacyHash],
            true,
        );

        $this->assertTrue($applied);
        $customer->refresh();
        $this->assertTrue(Hash::check('4321', $customer->password));
        $this->assertTrue($customer->metadata['legacy_password_migrated']);
    }
}
