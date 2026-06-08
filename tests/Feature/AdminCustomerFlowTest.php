<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\Market;
use App\Models\Province;
use App\Models\District;
use App\Notifications\CustomerRegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCustomerFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(5));

        $company = Company::create([
            'name' => 'Test Co '.$suffix,
            'slug' => 'test-co-'.$suffix,
            'code' => 'T'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Alice',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_character_customer_flow_creates_record_and_verifies_kyc(): void
    {
        config(['approval.customers.create' => false]); // allow auto-activation after KYC
        Storage::fake('public');
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['customers.create', 'kyc.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Loan',
            'code' => 'CHAR-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Standard',
            'code' => 'CHAR-GRP',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $province = Province::create([
            'name' => 'Lusaka',
            'code' => 'LSK',
            'country' => 'Zambia',
            'is_active' => true,
        ]);

        $referrer = Customer::create([
            'loan_product_id' => $product->id,
            'first_name' => 'Referral',
            'last_name' => 'Source',
            'email' => 'referrer@example.com',
            'password' => '1234',
            'phone' => '260955099999',
            'tpin' => '99999999',
        ]);

        $payload = [
            'loan_product_id' => $product->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '260955000001',
            'national_id_type' => 'nrc',
            'national_id' => '123456/78/1',
            'tpin' => '12345678',
            'address_line1' => '123 Main St',
            'city' => 'Lusaka',
            'country' => 'Zambia',
            'province_id' => $province->id,
            'referred_by' => $referrer->id,
            'customer_group_id' => $group->id,
            'next_of_kin_name' => 'Jane Doe',
            'next_of_kin_phone' => '260955000002',
            'next_of_kin_relationship' => 'Spouse',
            'is_employed' => true,
            'employee_number' => 'CHAR-EMP-001',
            'net_salary' => 5000,
        ];

        $response = $this->actingAs($admin, 'admin')->post(route('admin.customers.store'), $payload);
        $response->assertRedirect();

        $customer = Customer::where('email', 'john@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('pending', $customer->status);
        $this->assertEquals($group->id, $customer->customer_group_id);
        $this->assertEquals($referrer->id, $customer->referred_by);
        $this->assertEquals(3000.0, (float) $customer->maximum_loan_take); // 60% of 5000

        $kycResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.customers.kyc.store', $customer),
            [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
            ]
        );

        $kycResponse->assertRedirect(route('admin.customers.show', $customer));
        $customer->refresh();

        $this->assertEquals('active', $customer->status);
        $this->assertEquals('verified', $customer->kyc_status);
        $this->assertDatabaseHas('kyc_documents', [
            'customer_id' => $customer->id,
            'status' => 'verified',
        ]);
    }

    public function test_marketeer_requires_stand_picture_for_kyc(): void
    {
        config(['approval.customers.create' => false]); // keep flow simple for test
        Storage::fake('public');
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['customers.create', 'kyc.create']);

        $province = Province::create([
            'name' => 'Copperbelt',
            'code' => 'CB',
            'country' => 'Zambia',
            'is_active' => true,
        ]);

        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Kitwe',
            'code' => 'KTW',
            'is_active' => true,
        ]);

        $market = Market::create([
            'name' => 'Chisokone Market',
            'code' => 'MKT-1',
            'address_line1' => 'Stand 1',
            'city' => 'Kitwe',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'contact_person_name' => 'Manager',
            'contact_person_phone' => '260955000010',
            'is_active' => true,
        ]);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Marketeer Loan',
            'code' => 'MARK-001',
            'category' => 'marketeer',
            'is_active' => true,
        ]);

        $payload = [
            'loan_product_id' => $product->id,
            'first_name' => 'Mark',
            'last_name' => 'Trader',
            'email' => 'mark@example.com',
            'phone' => '260955000011',
            'national_id_type' => 'nrc',
            'national_id' => '987654/32/1',
            'tpin' => '87654321',
            'address_line1' => 'Shop 12',
            'city' => 'Kitwe',
            'country' => 'Zambia',
            'market_id' => $market->id,
            'next_of_kin_name' => 'Mary Trader',
            'next_of_kin_phone' => '260955000012',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_address_line1' => 'Plot 5',
            'next_of_kin_city' => 'Kitwe',
            'next_of_kin_country' => 'Zambia',
            'monthly_income' => 4000,
        ];

        $create = $this->actingAs($admin, 'admin')->post(route('admin.customers.store'), $payload);
        $create->assertRedirect();

        $customer = Customer::where('email', 'mark@example.com')->first();
        $this->assertNotNull($customer);

        // Missing stand_picture should fail validation
        $fail = $this->from(route('admin.customers.kyc.create', $customer))
            ->actingAs($admin, 'admin')
            ->post(route('admin.customers.kyc.store', $customer), [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
            ]);
        $fail->assertRedirect();
        $fail->assertSessionHasErrors('stand_picture');

        // Supplying stand_picture should succeed
        $pass = $this->actingAs($admin, 'admin')->post(
            route('admin.customers.kyc.store', $customer),
            [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
                'stand_picture' => UploadedFile::fake()->image('stand.jpg'),
            ]
        );
        $pass->assertRedirect(route('admin.customers.show', $customer));
    }

    public function test_when_customer_approval_is_required_create_and_kyc_do_not_send_onboarding_notification(): void
    {
        config(['approval.customers.create' => true]);
        Storage::fake('public');
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['customers.create', 'kyc.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Loan Approval Required',
            'code' => 'CHAR-APR-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Approval Group',
            'code' => 'CHAR-APR-GRP',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $province = Province::create([
            'name' => 'Northern',
            'code' => 'NOR',
            'country' => 'Zambia',
            'is_active' => true,
        ]);

        $payload = [
            'loan_product_id' => $product->id,
            'first_name' => 'Pending',
            'last_name' => 'Applicant',
            'email' => 'pending.applicant@example.com',
            'phone' => '260955444001',
            'national_id_type' => 'nrc',
            'national_id' => '998877/66/1',
            'tpin' => '22114455',
            'address_line1' => 'Plot 90',
            'city' => 'Kasama',
            'country' => 'Zambia',
            'province_id' => $province->id,
            'customer_group_id' => $group->id,
            'next_of_kin_name' => 'Helper Person',
            'next_of_kin_phone' => '260955444002',
            'next_of_kin_relationship' => 'Sibling',
            'is_employed' => true,
            'employee_number' => 'CHAR-EMP-99',
            'net_salary' => 3500,
        ];

        $createResponse = $this->actingAs($admin, 'admin')->post(route('admin.customers.store'), $payload);
        $createResponse->assertRedirect();

        $customer = Customer::where('email', 'pending.applicant@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('pending', $customer->approval_status);
        $this->assertEquals('pending', $customer->status);
        Notification::assertNotSentTo($customer, CustomerRegistrationNotification::class);

        $kycResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.customers.kyc.store', $customer),
            [
                'document_type' => 'nrc',
                'front_image' => UploadedFile::fake()->image('front.jpg'),
            ]
        );
        $kycResponse->assertRedirect(route('admin.customers.show', $customer));

        $customer->refresh();
        $this->assertEquals('pending', $customer->approval_status);
        $this->assertEquals('pending', $customer->status);
        Notification::assertNotSentTo($customer, CustomerRegistrationNotification::class);
    }

    public function test_government_create_validates_phone_salary_and_contract_dates(): void
    {
        $admin = $this->makeAdminWithPermissions(['customers.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Government Loan',
            'code' => 'GOV-001',
            'category' => 'government',
            'is_active' => true,
        ]);

        $ministry = \App\Models\Ministry::create([
            'name' => 'Ministry of Test',
            'code' => 'MOT',
            'description' => 'Test ministry',
            'is_active' => true,
        ]);

        $relationshipManager = Admin::create([
            'company_id' => $admin->company_id,
            'first_name' => 'Rel',
            'last_name' => 'Manager',
            'email' => 'rm@example.com',
            'password' => 'password',
            'is_relationship_manager' => true,
            'is_active' => true,
        ]);

        $payload = [
            'loan_product_id' => $product->id,
            'first_name' => 'Gov',
            'last_name' => 'Worker',
            'email' => 'gov.worker@example.com',
            'phone' => '+260955100001',
            'national_id_type' => 'nrc',
            'national_id' => '123456/11/1',
            'tpin' => '11112222',
            'address_line1' => 'Plot 1',
            'city' => 'Lusaka',
            'country' => 'Zambia',
            'employee_number' => 'GOV-EMP-001',
            'ministry_id' => $ministry->id,
            'date_of_employment' => '2025-01-01',
            'contract_end_date' => '2024-12-31',
            'gross_salary' => 5000,
            'net_salary' => 4000,
            'verified_by' => $relationshipManager->id,
            'next_of_kin_name' => 'Jane Kin',
            'next_of_kin_phone' => '260955100099',
            'next_of_kin_relationship' => 'spouse',
        ];

        $response = $this->from(route('admin.customers.create', ['product_id' => $product->id]))
            ->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['phone', 'contract_end_date']);

        $salaryPayload = $payload;
        $salaryPayload['phone'] = '260955100001';
        $salaryPayload['contract_end_date'] = '2025-12-31';
        $salaryPayload['email'] = 'gov.worker2@example.com';
        $salaryPayload['national_id_type'] = 'nrc';
        $salaryPayload['national_id'] = '123456/11/2';
        $salaryPayload['tpin'] = '11112223';
        $salaryPayload['net_salary'] = 6000;

        $salaryResponse = $this->from(route('admin.customers.create', ['product_id' => $product->id]))
            ->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $salaryPayload);

        $salaryResponse->assertRedirect();
        $salaryResponse->assertSessionHasErrors(['net_salary']);
    }

    public function test_sme_company_uses_net_revenue_and_percentage_for_qualification(): void
    {
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['customers.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'SME Working Capital',
            'code' => 'SME-QUAL-001',
            'category' => 'sme',
            'is_active' => true,
        ]);

        $payload = [
            'loan_product_id' => $product->id,
            'customer_type' => 'company',
            'company_id' => $admin->company_id,
            'registered_name' => 'Bluebird Traders',
            'email' => 'bluebird@example.com',
            'phone' => '260955222001',
            'tpin' => '12344321',
            'monthly_net_revenue' => 10000,
            'qualification_percentage' => 40,
        ];

        $create = $this->actingAs($admin, 'admin')->post(route('admin.customers.store'), $payload);
        $create->assertRedirect();

        $customer = Customer::where('email', 'bluebird@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('company', $customer->customer_type);
        $this->assertEquals(10000.0, (float) $customer->net_salary);
        $this->assertEquals(4000.0, (float) $customer->maximum_loan_take);
        $this->assertEquals(40.0, (float) data_get($customer->metadata, 'sme_qualification_percentage'));
    }

    public function test_sme_representative_requires_and_saves_national_id(): void
    {
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['customers.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'SME Working Capital',
            'code' => 'SME-001',
            'category' => 'sme',
            'is_active' => true,
        ]);

        $parentCompanyCustomer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'customer_type' => 'company',
            'registered_name' => 'Acme Limited',
            'first_name' => 'Acme Limited',
            'last_name' => 'Acme Limited',
            'email' => 'acme@example.com',
            'password' => '1234',
            'tpin' => '55554444',
        ]);

        $payload = [
            'loan_product_id' => $product->id,
            'customer_type' => 'representative',
            'company_id' => $admin->company_id,
            'parent_customer_id' => $parentCompanyCustomer->id,
            'first_name' => 'Jane',
            'last_name' => 'Signer',
            'email' => 'jane.signer@example.com',
            'phone' => '260955111001',
            'tpin' => '55554445',
        ];

        $missingNationalId = $this->from(route('admin.customers.create', ['product_id' => $product->id]))
            ->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), $payload);

        $missingNationalId->assertRedirect();
        $missingNationalId->assertSessionHasErrors(['national_id']);

        $payload['national_id_type'] = 'nrc';
        $payload['national_id'] = '445566/77/8';

        $create = $this->actingAs($admin, 'admin')->post(route('admin.customers.store'), $payload);
        $create->assertRedirect();

        $representative = Customer::where('email', 'jane.signer@example.com')->first();
        $this->assertNotNull($representative);
        $this->assertEquals('445566/77/8', $representative->national_id);
        $this->assertEquals('representative', $representative->customer_type);
    }

    public function test_sme_company_update_does_not_require_first_and_last_name_keys(): void
    {
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['customers.update']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'SME Update Product',
            'code' => 'SME-UPD-001',
            'category' => 'sme',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'customer_type' => 'company',
            'registered_name' => 'Original Traders',
            'first_name' => 'Original Traders',
            'last_name' => 'Original Traders',
            'email' => 'sme-update@example.com',
            'phone' => '260955333001',
            'password' => '1234',
            'tpin' => '12345679',
            'status' => 'active',
            'approval_status' => 'approved',
            'net_salary' => 8000,
            'maximum_loan_take' => 4800,
            'metadata' => [
                'sme_qualification_percentage' => 60,
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')->put(
            route('admin.customers.update', $customer),
            [
                'loan_product_id' => $product->id,
                'customer_type' => 'company',
                'company_id' => $admin->company_id,
                'registered_name' => 'Updated Traders',
                'monthly_net_revenue' => 10000,
                'qualification_percentage' => 40,
                'email' => 'sme-update@example.com',
                'phone' => '260955333001',
                'tpin' => '12345679',
            ]
        );

        $response->assertRedirect(route('admin.customers.show', $customer));

        $customer->refresh();
        $this->assertEquals('Updated Traders', $customer->registered_name);
        $this->assertEquals('Updated Traders', $customer->first_name);
        $this->assertEquals('Updated Traders', $customer->last_name);
        $this->assertEquals(10000.0, (float) $customer->net_salary);
        $this->assertEquals(4000.0, (float) $customer->maximum_loan_take);
        $this->assertEquals(40.0, (float) data_get($customer->metadata, 'sme_qualification_percentage'));
    }
}
