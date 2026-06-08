<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\Ministry;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerEmployeeNumberTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'Emp Co '.$suffix,
            'slug' => 'emp-'.$suffix,
            'code' => 'EM'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Emp',
            'last_name' => 'Admin',
            'email' => 'emp-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        Permission::firstOrCreate(['name' => 'customers.create', 'guard_name' => 'admin']);
        $admin->givePermissionTo('customers.create');

        return $admin;
    }

    public function test_government_customer_requires_employee_number(): void
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
            'name' => 'Health',
            'code' => 'MOH-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), [
                'loan_product_id' => $product->id,
                'first_name' => 'Gov',
                'last_name' => 'Worker',
                'email' => 'gov-'.Str::random(5).'@example.com',
                'national_id_type' => 'nrc',
                'national_id' => '123456/78/1',
                'address_line1' => 'Line 1',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'ministry_id' => $ministry->id,
                'date_of_employment' => '2020-01-01',
                'gross_salary' => 10000,
                'net_salary' => 8000,
                'verified_by' => $admin->id,
            ])
            ->assertSessionHasErrors('employee_number');
    }

    public function test_character_employed_customer_requires_employee_number(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character',
            'code' => 'CHAR-'.Str::upper(Str::random(3)),
            'category' => 'character',
            'is_active' => true,
        ]);
        $province = Province::create([
            'name' => 'Lusaka',
            'code' => 'LSK',
            'country' => 'Zambia',
            'is_active' => true,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Default',
            'code' => 'DEF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), [
                'loan_product_id' => $product->id,
                'first_name' => 'Char',
                'last_name' => 'Employed',
                'email' => 'char-'.Str::random(5).'@example.com',
                'national_id_type' => 'nrc',
                'national_id' => '987654/32/1',
                'address_line1' => 'Line 1',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'province_id' => $province->id,
                'customer_group_id' => $group->id,
                'next_of_kin_name' => 'Kin',
                'next_of_kin_phone' => '260978232335',
                'next_of_kin_relationship' => 'Sibling',
                'is_employed' => true,
                'net_salary' => 4000,
            ])
            ->assertSessionHasErrors('employee_number');
    }

    public function test_character_unemployed_customer_does_not_require_employee_number(): void
    {
        $admin = $this->admin();
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character',
            'code' => 'CHAR-'.Str::upper(Str::random(3)),
            'category' => 'character',
            'is_active' => true,
        ]);
        $province = Province::create([
            'name' => 'Lusaka',
            'code' => 'LSK',
            'country' => 'Zambia',
            'is_active' => true,
        ]);
        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Default',
            'code' => 'DEF-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), [
                'loan_product_id' => $product->id,
                'first_name' => 'Char',
                'last_name' => 'Self',
                'email' => 'char-'.Str::random(5).'@example.com',
                'national_id_type' => 'nrc',
                'national_id' => '111111/11/1',
                'address_line1' => 'Line 1',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'province_id' => $province->id,
                'customer_group_id' => $group->id,
                'next_of_kin_name' => 'Kin',
                'next_of_kin_phone' => '260978232336',
                'next_of_kin_relationship' => 'Sibling',
                'is_employed' => false,
                'net_salary' => 2000,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
