<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\GeneralSetting;
use App\Models\LoanProduct;
use App\Models\Market;
use App\Models\Ministry;
use App\Models\District;
use App\Models\Province;
use App\Support\NationalIdRules;
use App\Support\PublicRegistrationPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerNationalIdValidationTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_NRC = '123456/78/1';

    private function admin(array $permissions = ['customers.create', 'customers.update']): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'National ID Co '.$suffix,
            'slug' => 'nid-'.$suffix,
            'code' => 'NID'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'ID',
            'last_name' => 'Admin',
            'email' => 'nid-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function governmentPayload(Admin $admin, LoanProduct $product, Ministry $ministry, array $overrides = []): array
    {
        return array_merge([
            'loan_product_id' => $product->id,
            'first_name' => 'Gov',
            'last_name' => 'Employee',
            'email' => 'gov-'.Str::random(5).'@example.com',
            'phone' => '260978232334',
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'address_line1' => 'Line 1',
            'city' => 'Lusaka',
            'country' => 'Zambia',
            'employee_number' => 'EMP-10001',
            'ministry_id' => $ministry->id,
            'date_of_employment' => '2020-01-01',
            'gross_salary' => 10000,
            'net_salary' => 8000,
            'verified_by' => $admin->id,
        ], $overrides);
    }

    public function test_customer_creation_succeeds_without_tpin(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'national_id' => self::VALID_NRC,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'tpin' => null,
        ]);
    }

    public function test_customer_creation_fails_without_national_id(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry, [
                'national_id' => '',
            ]))
            ->assertSessionHasErrors('national_id');
    }

    public function test_customer_creation_fails_without_national_id_type(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry, [
                'national_id_type' => '',
            ]))
            ->assertSessionHasErrors('national_id_type');
    }

    public function test_nrc_type_accepts_valid_format(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry, [
                'national_id' => '111111/11/1',
            ]))
            ->assertSessionHasNoErrors();
    }

    /**
     * @dataProvider invalidNrcProvider
     */
    public function test_nrc_type_rejects_invalid_formats(string $invalidNrc): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry, [
                'national_id' => $invalidNrc,
            ]))
            ->assertSessionHasErrors('national_id');
    }

  /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNrcProvider(): array
    {
        return [
            'no slashes' => ['1111111111'],
            'dashes' => ['111111-11-1'],
            'short segment' => ['111111/1/1'],
            'letters' => ['ABC111/11/1'],
        ];
    }

    public function test_passport_type_accepts_non_nrc_value(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry, [
                'national_id_type' => NationalIdRules::TYPE_PASSPORT,
                'national_id' => 'P12345678',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_drivers_licence_type_accepts_non_nrc_value(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry, [
                'national_id_type' => NationalIdRules::TYPE_DRIVERS_LICENCE,
                'national_id' => 'DL-998877',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_customer_update_follows_same_rules(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Finance',
            'code' => 'MOF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $this->governmentPayload($admin, $product, $ministry));

        $customer = Customer::where('national_id', self::VALID_NRC)->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.customers.update', $customer), $this->governmentPayload($admin, $product, $ministry, [
                'email' => $customer->email,
                'national_id' => 'not-valid-nrc',
            ]))
            ->assertSessionHasErrors('national_id');
    }

    public function test_public_registration_request_requires_national_id_type(): void
    {
        $company = Company::create([
            'name' => 'Reg Co',
            'slug' => 'reg-co',
            'code' => 'REGCO',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Government',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);

        GeneralSetting::create([
            'allow_customer_registration' => true,
            'public_registration_paths' => [
                \App\Support\PublicRegistrationPaths::GOVERNMENT_WORKER => [
                    'enabled' => true,
                    'loan_product_id' => $product->id,
                ],
                \App\Support\PublicRegistrationPaths::COLLATERAL_BASED => [
                    'enabled' => false,
                    'loan_product_id' => null,
                ],
            ],
            'repayment_reminders_enabled' => true,
            'remind_1_week_before' => false,
            'remind_2_days_before' => false,
            'remind_1_day_before' => false,
            'missed_payment_reminder_count' => 1,
        ]);

        $province = Province::create(['name' => 'Lusaka', 'code' => 'LSK-R', 'country' => 'Zambia', 'is_active' => true]);
        $district = District::create(['province_id' => $province->id, 'name' => 'Lusaka D', 'code' => 'LSK-DR', 'is_active' => true]);

        $this->post(route('customer.register-request.government-worker.store'), [
            'first_name' => 'Self',
            'last_name' => 'Register',
            'email' => 'self.register@example.com',
            'phone' => '260978232334',
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 1000,
            'ministry_id' => PublicRegistrationPaths::MINISTRY_OTHER,
            'employer_name' => 'MOF',
            'employee_number' => 'E1',
            'net_salary' => 5000,
            'work_address_line1' => 'Office 1',
            'work_province_id' => $province->id,
            'work_district_id' => $district->id,
        ])->assertSessionHasErrors('national_id_type');
    }

    public function test_marketeer_customer_creation_follows_national_id_rules(): void
    {
        $admin = $this->admin();
        $province = Province::create([
            'name' => 'Lusaka',
            'code' => 'LSK',
            'country' => 'Zambia',
            'is_active' => true,
        ]);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Lusaka District',
            'code' => 'LSK-D',
            'is_active' => true,
        ]);
        $market = Market::create([
            'name' => 'City Market',
            'code' => 'MKT-'.Str::random(3),
            'address_line1' => 'Stand 1',
            'city' => 'Lusaka',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'contact_person_name' => 'Manager',
            'contact_person_phone' => '260955000010',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Marketeer',
            'code' => 'MKT-'.Str::upper(Str::random(3)),
            'category' => 'marketeer',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), [
                'loan_product_id' => $product->id,
                'first_name' => 'Mark',
                'last_name' => 'Trader',
                'email' => 'mark-'.Str::random(5).'@example.com',
                'national_id_type' => NationalIdRules::TYPE_PASSPORT,
                'national_id' => 'ZN12345',
                'address_line1' => 'Shop 1',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'market_id' => $market->id,
                'next_of_kin_name' => 'Kin Name',
                'next_of_kin_phone' => '260955000011',
                'next_of_kin_relationship' => 'Sibling',
                'next_of_kin_address_line1' => 'Plot 1',
                'next_of_kin_city' => 'Lusaka',
                'next_of_kin_country' => 'Zambia',
                'monthly_income' => 3000,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
