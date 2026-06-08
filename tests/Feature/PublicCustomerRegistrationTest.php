<?php

namespace Tests\Feature;

use App\Models\CollateralType;
use App\Models\Company;
use App\Models\CustomerRegistrationRequest;
use App\Models\FinancialInstitution;
use App\Models\GeneralSetting;
use App\Models\LoanProduct;
use App\Models\District;
use App\Models\Ministry;
use App\Models\Province;
use App\Support\NationalIdRules;
use App\Support\PublicRegistrationPaths;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PublicCustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_NRC = '123456/78/1';

    private const VALID_PHONE = '260978232334';

    private const VALID_EMAIL = 'applicant@example.com';

    /**
     * @return array{setting: GeneralSetting, government: LoanProduct, collateral: LoanProduct, collateralType: CollateralType}
     */
    private function enableBothPaths(): array
    {
        $company = Company::create([
            'name' => 'Reg Co',
            'slug' => 'reg-co-'.Str::random(4),
            'code' => 'REG'.Str::upper(Str::random(3)),
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $government = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Gov Payroll',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);

        $collateral = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Collateral Loan',
            'code' => 'COL-'.Str::upper(Str::random(3)),
            'category' => 'collateral',
            'is_active' => true,
        ]);

        $collateralType = CollateralType::create([
            'loan_product_id' => $collateral->id,
            'name' => 'Vehicle',
            'code' => 'VEH',
            'category' => 'Vehicle',
            'is_active' => true,
        ]);

        $setting = GeneralSetting::create([
            'allow_customer_registration' => true,
            'public_registration_paths' => [
                PublicRegistrationPaths::GOVERNMENT_WORKER => [
                    'enabled' => true,
                    'loan_product_id' => $government->id,
                ],
                PublicRegistrationPaths::COLLATERAL_BASED => [
                    'enabled' => true,
                    'loan_product_id' => $collateral->id,
                ],
            ],
            'repayment_reminders_enabled' => false,
            'missed_payment_reminder_count' => 1,
        ]);

        return compact('setting', 'government', 'collateral', 'collateralType');
    }

    /**
     * @return array<string, int|string>
     */
    private function governmentBankFields(): array
    {
        $this->seed(FinancialInstitutionSeeder::class);
        $institution = FinancialInstitution::where('code', 'ZANACO')->firstOrFail();
        $branch = $institution->branches()->where('name', 'Main Branch')->firstOrFail();

        return [
            'bank_financial_institution_id' => $institution->id,
            'bank_financial_institution_branch_id' => $branch->id,
            'bank_account_name' => 'Test Applicant',
            'bank_account_number' => '1234567890',
        ];
    }

    public function test_registration_disabled_returns_not_found(): void
    {
        GeneralSetting::create([
            'allow_customer_registration' => false,
            'repayment_reminders_enabled' => false,
            'missed_payment_reminder_count' => 1,
        ]);

        $this->get(route('customer.register-request.create'))->assertNotFound();
    }

    public function test_registration_enabled_without_paths_shows_unavailable(): void
    {
        GeneralSetting::create([
            'allow_customer_registration' => true,
            'public_registration_paths' => PublicRegistrationPaths::normalize(null),
            'repayment_reminders_enabled' => false,
            'missed_payment_reminder_count' => 1,
        ]);

        $this->get(route('customer.register-request.create'))
            ->assertOk()
            ->assertViewIs('customer.registration.unavailable');
    }

    public function test_path_selection_shows_enabled_paths(): void
    {
        $this->enableBothPaths();

        $this->get(route('customer.register-request.create'))
            ->assertOk()
            ->assertViewIs('customer.registration.choose-path')
            ->assertSee('Government Worker', false)
            ->assertSee('Collateral-Based Registration', false);
    }

    public function test_disabled_government_path_cannot_be_accessed(): void
    {
        $data = $this->enableBothPaths();
        $data['setting']->update([
            'public_registration_paths' => [
                PublicRegistrationPaths::GOVERNMENT_WORKER => ['enabled' => false, 'loan_product_id' => null],
                PublicRegistrationPaths::COLLATERAL_BASED => [
                    'enabled' => true,
                    'loan_product_id' => $data['collateral']->id,
                ],
            ],
        ]);

        $this->get(route('customer.register-request.government-worker.create'))->assertNotFound();
    }

    public function test_government_form_does_not_show_product_or_group_fields(): void
    {
        $this->enableBothPaths();

        $response = $this->get(route('customer.register-request.government-worker.create'));
        $response->assertOk();
        $response->assertDontSee('loan_product_id', false);
        $response->assertDontSee('customer_group_id', false);
        $response->assertSee('Government Worker Registration', false);
    }

    public function test_collateral_form_does_not_show_product_or_group_fields(): void
    {
        $this->enableBothPaths();

        $response = $this->get(route('customer.register-request.collateral-based.create'));
        $response->assertOk();
        $response->assertDontSee('loan_product_id', false);
        $response->assertDontSee('customer_group_id', false);
        $response->assertSee('Collateral-Based Registration', false);
    }

    public function test_government_request_stores_path_and_employment_details(): void
    {
        $data = $this->enableBothPaths();
        $ministry = Ministry::create([
            'name' => 'Ministry of Finance',
            'code' => 'MOF',
            'is_active' => true,
        ]);
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

        $this->post(route('customer.register-request.government-worker.store'), array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Gov',
            'email' => self::VALID_EMAIL,
            'phone' => self::VALID_PHONE,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 5000,
            'address_line1' => '12 Home Street',
            'city' => 'Lusaka',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'country' => 'Zambia',
            'ministry_id' => $ministry->id,
            'employee_number' => 'EMP-9001',
            'date_of_employment' => '2018-06-01',
            'net_salary' => 8000,
            'work_address_line1' => 'Government Complex',
            'work_province_id' => $province->id,
            'work_district_id' => $district->id,
            'loan_product_id' => $data['collateral']->id,
            'customer_group_id' => 99999,
        ], $this->governmentBankFields()))->assertRedirect(route('customer.register-request.thank-you'));

        $request = CustomerRegistrationRequest::query()
            ->where('registration_path', PublicRegistrationPaths::GOVERNMENT_WORKER)
            ->where('first_name', 'Jane')
            ->first();
        $this->assertNotNull($request);
        $this->assertSame(PublicRegistrationPaths::GOVERNMENT_WORKER, $request->registration_path);
        $this->assertSame($data['government']->id, $request->loan_product_id, 'Client loan_product_id must be ignored');
        $this->assertNull($request->customer_group_id);
        $this->assertSame('5000.00', (string) $request->requested_loan_amount);
        $this->assertSame('EMP-9001', $request->employment_details['employee_number']);
        $this->assertSame($ministry->id, $request->employment_details['ministry_id']);
        $this->assertSame('GOVERNMENT COMPLEX', $request->employment_details['work_address_line1']);
        $this->assertSame($province->id, $request->employment_details['work_province_id']);
        $this->assertSame('12 HOME STREET', $request->employment_details['address_line1']);
        $this->assertSame('2018-06-01', $request->employment_details['date_of_employment']);
    }

    public function test_government_other_ministry_requires_employer_name(): void
    {
        $data = $this->enableBothPaths();
        Ministry::create(['name' => 'MOH', 'code' => 'MOH', 'is_active' => true]);
        $province = Province::create(['name' => 'Copperbelt', 'code' => 'CB', 'country' => 'Zambia', 'is_active' => true]);
        $district = District::create(['province_id' => $province->id, 'name' => 'Ndola', 'code' => 'NDL', 'is_active' => true]);

        $this->post(route('customer.register-request.government-worker.store'), array_merge([
            'first_name' => 'Other',
            'last_name' => 'Ministry',
            'email' => 'other.ministry@example.com',
            'phone' => self::VALID_PHONE,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 3000,
            'address_line1' => 'Plot 1',
            'city' => 'Ndola',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'country' => 'Zambia',
            'ministry_id' => PublicRegistrationPaths::MINISTRY_OTHER,
            'employer_name' => 'Custom Agency Ltd',
            'employee_number' => 'EMP-X',
            'date_of_employment' => '2020-01-15',
            'net_salary' => 4000,
            'work_address_line1' => 'Plot 10',
            'work_province_id' => $province->id,
            'work_district_id' => $district->id,
        ], $this->governmentBankFields()))->assertRedirect(route('customer.register-request.thank-you'));

        $request = CustomerRegistrationRequest::query()->where('first_name', 'Other')->first();
        $this->assertTrue($request->employment_details['ministry_is_other']);
        $this->assertNull($request->employment_details['ministry_id']);
        $this->assertSame('CUSTOM AGENCY LTD', $request->employment_details['employer_name']);
    }

    public function test_collateral_request_stores_path_and_collateral_details(): void
    {
        $data = $this->enableBothPaths();

        $province = Province::create([
            'name' => 'Southern',
            'code' => 'SOUTH',
            'country' => 'Zambia',
            'is_active' => true,
        ]);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Livingstone',
            'code' => 'LIV',
            'is_active' => true,
        ]);

        $this->post(route('customer.register-request.collateral-based.store'), [
            'first_name' => 'John',
            'last_name' => 'Col',
            'email' => 'john.col@example.com',
            'phone' => self::VALID_PHONE,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 10000,
            'address_line1' => '12 Main Road',
            'city' => 'Livingstone',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'country' => 'Zambia',
            'collateral_type_id' => $data['collateralType']->id,
            'collateral_description' => 'Toyota Hilux 2020',
            'estimated_collateral_value' => 150000,
            'loan_product_id' => $data['government']->id,
        ])->assertRedirect(route('customer.register-request.thank-you'));

        $request = CustomerRegistrationRequest::query()
            ->where('registration_path', PublicRegistrationPaths::COLLATERAL_BASED)
            ->where('first_name', 'John')
            ->first();
        $this->assertSame(PublicRegistrationPaths::COLLATERAL_BASED, $request->registration_path);
        $this->assertSame($data['collateral']->id, $request->loan_product_id, 'Client loan_product_id must be ignored');
        $this->assertSame('TOYOTA HILUX 2020', $request->collateral_details['collateral_description']);
        $this->assertSame($data['collateralType']->id, $request->collateral_details['collateral_type_id']);
        $this->assertSame('12 MAIN ROAD', $request->collateral_details['address_line1']);
        $this->assertSame($province->id, $request->collateral_details['province_id']);
    }

    public function test_email_is_required_on_registration_request(): void
    {
        $this->enableBothPaths();
        Ministry::create(['name' => 'MOF', 'code' => 'MOF3', 'is_active' => true]);
        $province = Province::create(['name' => 'Lusaka', 'code' => 'LSK3', 'country' => 'Zambia', 'is_active' => true]);
        $district = District::create(['province_id' => $province->id, 'name' => 'Lusaka D3', 'code' => 'LSK-D3', 'is_active' => true]);

        $this->post(route('customer.register-request.government-worker.store'), [
            'first_name' => 'No',
            'last_name' => 'Email',
            'phone' => self::VALID_PHONE,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 1000,
            'ministry_id' => PublicRegistrationPaths::MINISTRY_OTHER,
            'employer_name' => 'Ministry X',
            'employee_number' => 'E1',
            'net_salary' => 5000,
            'work_address_line1' => 'Plot 1',
            'work_province_id' => $province->id,
            'work_district_id' => $district->id,
        ])->assertSessionHasErrors('email');
    }

    public function test_phone_validation_required_and_format(): void
    {
        $this->enableBothPaths();
        Ministry::create(['name' => 'MOF', 'code' => 'MOF2', 'is_active' => true]);
        $province = Province::create(['name' => 'Lusaka', 'code' => 'LSK2', 'country' => 'Zambia', 'is_active' => true]);
        $district = District::create(['province_id' => $province->id, 'name' => 'Lusaka D2', 'code' => 'LSK-D2', 'is_active' => true]);

        $this->post(route('customer.register-request.government-worker.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => self::VALID_EMAIL,
            'phone' => '0978232334',
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 1000,
            'ministry_id' => PublicRegistrationPaths::MINISTRY_OTHER,
            'employer_name' => 'Ministry X',
            'employee_number' => 'E1',
            'net_salary' => 5000,
            'work_address_line1' => 'Plot 1',
            'work_province_id' => $province->id,
            'work_district_id' => $district->id,
        ])->assertSessionHasErrors('phone');
    }

    public function test_nrc_validation_when_type_is_nrc(): void
    {
        $this->enableBothPaths();

        $this->post(route('customer.register-request.collateral-based.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => self::VALID_EMAIL,
            'phone' => self::VALID_PHONE,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => 'invalid',
            'requested_loan_amount' => 1000,
            'collateral_type_id' => CollateralType::first()->id,
            'collateral_description' => 'Desc',
            'estimated_collateral_value' => 5000,
        ])->assertSessionHasErrors('national_id');
    }

    public function test_tpin_is_optional(): void
    {
        $data = $this->enableBothPaths();

        $province = Province::create(['name' => 'Central', 'code' => 'CEN', 'country' => 'Zambia', 'is_active' => true]);
        $district = District::create(['province_id' => $province->id, 'name' => 'Kabwe', 'code' => 'KBW', 'is_active' => true]);

        $this->post(route('customer.register-request.collateral-based.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => self::VALID_EMAIL,
            'phone' => self::VALID_PHONE,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'national_id' => self::VALID_NRC,
            'requested_loan_amount' => 1000,
            'address_line1' => 'Plot 5',
            'city' => 'Kabwe',
            'province_id' => $province->id,
            'district_id' => $district->id,
            'country' => 'Zambia',
            'collateral_type_id' => $data['collateralType']->id,
            'collateral_description' => 'Desc',
            'estimated_collateral_value' => 5000,
        ])->assertRedirect(route('customer.register-request.thank-you'));

        $this->assertNull(
            CustomerRegistrationRequest::query()
                ->where('first_name', 'A')
                ->where('last_name', 'B')
                ->value('tpin')
        );
    }

    public function test_admin_review_shows_path_and_details(): void
    {
        $data = $this->enableBothPaths();
        $admin = \App\Models\Admin::create([
            'company_id' => $data['government']->company_id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.Str::random(5).'@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        Permission::firstOrCreate(['name' => 'customer-requests.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('customer-requests.view');

        $registration = CustomerRegistrationRequest::create([
            'reference' => 'CRR-TEST-001',
            'registration_path' => PublicRegistrationPaths::GOVERNMENT_WORKER,
            'loan_product_id' => $data['government']->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => self::VALID_PHONE,
            'national_id' => self::VALID_NRC,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'requested_loan_amount' => 7500,
            'status' => 'pending',
            'employment_details' => [
                'employee_number' => 'E-1',
                'employer_name' => 'MOH',
                'net_salary' => 6000,
            ],
            'payload' => [],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customer-requests.show', $registration))
            ->assertOk()
            ->assertSee('Government Worker')
            ->assertSee('Employment details')
            ->assertSee('E-1')
            ->assertSee('7,500.00');
    }

    public function test_approved_request_links_to_customer_create(): void
    {
        $data = $this->enableBothPaths();
        $admin = \App\Models\Admin::create([
            'company_id' => $data['government']->company_id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin2-'.Str::random(5).'@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        Permission::firstOrCreate(['name' => 'customer-requests.view', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'customers.create', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['customer-requests.view', 'customers.create']);

        $registration = CustomerRegistrationRequest::create([
            'reference' => 'CRR-TEST-002',
            'registration_path' => PublicRegistrationPaths::GOVERNMENT_WORKER,
            'loan_product_id' => $data['government']->id,
            'first_name' => 'Prefill',
            'last_name' => 'Test',
            'phone' => self::VALID_PHONE,
            'national_id' => self::VALID_NRC,
            'national_id_type' => NationalIdRules::TYPE_NRC,
            'requested_loan_amount' => 5000,
            'status' => 'approved',
            'employment_details' => ['employee_number' => 'E-99', 'net_salary' => 4000],
            'payload' => [],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customer-requests.show', $registration))
            ->assertSee('Create Customer');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.create', [
                'product_id' => $data['government']->id,
                'registration_request' => $registration->id,
            ]))
            ->assertOk();
    }

    public function test_login_shows_register_link_when_enabled(): void
    {
        $this->enableBothPaths();

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('Request to register');
    }
}
