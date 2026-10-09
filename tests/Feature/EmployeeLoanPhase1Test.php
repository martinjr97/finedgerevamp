<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\Repayment;
use App\Services\PublicWebsiteCatalogService;
use App\Support\HrPermissions;
use Database\Seeders\CompanySeeder;
use Database\Seeders\EmployeeLoanProductSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeLoanPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(CompanySeeder::class);
        $this->seed(HrSeeder::class);
        $this->seed(EmployeeLoanProductSeeder::class);
    }

    private function makeAdmin(array $permissions = []): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'EL Co '.$suffix,
            'slug' => 'el-co-'.$suffix,
            'code' => 'EL'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'el-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    /**
     * @return array{employee: Employee, loanRate: LoanRate|null, employeeLoan: EmployeeLoan}
     */
    private function createActiveEmployeeLoan(array $overrides = []): array
    {
        $employee = Employee::create([
            'first_name' => 'Staff',
            'last_name' => 'Borrower',
            'employee_number' => 'EMP-EL-'.Str::upper(Str::random(4)),
            'employment_status' => 'active',
        ]);

        $loanRate = LoanRate::query()->whereHas('loanRateType', fn ($q) => $q->where('code', 'EMPLOYEE_RATE'))->first();

        $loanNumber = 'EL-'.Str::upper(Str::random(8));

        $employeeLoan = EmployeeLoan::create(array_merge([
            'loan_number' => $loanNumber,
            'employee_id' => $employee->id,
            'loan_rate_id' => $loanRate?->id,
            'status' => EmployeeLoan::STATUS_ACTIVE,
            'disbursement_status' => 'completed',
            'principal_amount' => 20000,
            'processing_fee' => 0,
            'total_amount' => 22000,
            'outstanding_balance' => 18000,
            'tenure_months' => 12,
            'repayment_frequency' => 'monthly',
            'accrual_type' => 'at_beginning',
            'disbursed_at' => now(),
            'application_date' => now()->toDateString(),
        ], $overrides));

        return compact('employee', 'loanRate', 'employeeLoan');
    }

    private function createActiveCustomerLoan(): Loan
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'Cust Co '.$suffix,
            'slug' => 'cust-co-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Retail Product',
            'code' => 'RP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Retail',
            'last_name' => 'Customer',
            'email' => 'retail-'.$suffix.'@example.com',
            'phone' => '26097'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'total_amount' => 5500,
            'outstanding_balance' => 5500,
            'tenure_months' => 6,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(6)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
        ]);
    }

    public function test_employee_loan_does_not_affect_customer_active_portfolio_count(): void
    {
        $customerLoan = $this->createActiveCustomerLoan();
        $before = Loan::query()->activePortfolio()->count();
        $this->assertSame(1, $before);

        $this->createActiveEmployeeLoan();

        $this->assertSame(1, Loan::query()->activePortfolio()->count());
        $this->assertSame(1, EmployeeLoan::query()->activePortfolio()->count());
        $this->assertDatabaseHas('loans', ['loan_number' => $customerLoan->loan_number]);
    }

    public function test_employee_loan_excluded_from_loan_book_active_portfolio_report(): void
    {
        $admin = $this->makeAdmin(['reports.view', 'loans.view']);
        $customerLoan = $this->createActiveCustomerLoan();
        $context = $this->createActiveEmployeeLoan();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.loan-book'));

        $response->assertOk();
        $response->assertDontSee($context['employeeLoan']->loan_number);
        $this->assertSame(1, Loan::query()->activePortfolio()->count());
    }

    public function test_employee_loan_not_in_customer_loan_index_search(): void
    {
        $admin = $this->makeAdmin(['loans.view']);
        $customerLoan = $this->createActiveCustomerLoan();
        $context = $this->createActiveEmployeeLoan();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.index', ['search' => $context['employeeLoan']->loan_number]))
            ->assertOk()
            ->assertSee('No loans found.');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.index', ['search' => $customerLoan->loan_number]))
            ->assertOk()
            ->assertSee($customerLoan->loan_number);
    }

    public function test_employee_loan_not_in_loans_table_or_par_portfolio_query(): void
    {
        $context = $this->createActiveEmployeeLoan();
        $number = $context['employeeLoan']->loan_number;

        $this->assertDatabaseMissing('loans', ['loan_number' => $number]);

        $portfolioNumbers = Loan::query()->activePortfolio()->pluck('loan_number');
        $this->assertFalse($portfolioNumbers->contains($number));
    }

    public function test_employee_loan_does_not_create_customer_repayment_rows(): void
    {
        $beforeRepayments = Repayment::query()->count();
        $beforeLoanRepayments = \App\Models\LoanRepayment::query()->count();

        $this->createActiveEmployeeLoan();

        $this->assertSame($beforeRepayments, Repayment::query()->count());
        $this->assertSame($beforeLoanRepayments, \App\Models\LoanRepayment::query()->count());
        $this->assertDatabaseCount('employee_loans', 1);
    }

    public function test_employee_loan_relationships_and_soft_delete_employee_preserves_loan(): void
    {
        $context = $this->createActiveEmployeeLoan();
        $employee = $context['employee'];
        $loan = $context['employeeLoan'];

        $this->assertTrue($employee->employeeLoans()->whereKey($loan->id)->exists());
        $this->assertSame($employee->id, $loan->employee()->first()->id);

        if ($context['loanRate']) {
            $this->assertSame($context['loanRate']->id, $loan->loanRate()->first()->id);
        }

        $employee->delete();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employee_loans', ['id' => $loan->id, 'employee_id' => $employee->id]);
    }

    public function test_hr_employee_loans_view_permission_grants_index_access(): void
    {
        $admin = $this->makeAdmin(['hr.employee-loans.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hr.employee-loans.index'))
            ->assertOk()
            ->assertSee('Employee Loans');
    }

    public function test_loans_view_alone_does_not_grant_employee_loans_index(): void
    {
        $admin = $this->makeAdmin(['loans.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hr.employee-loans.index'))
            ->assertForbidden();
    }

    public function test_hr_employees_view_alone_does_not_grant_employee_loans_index(): void
    {
        $admin = $this->makeAdmin(['hr.employees.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hr.employee-loans.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_employee_loans_index(): void
    {
        $admin = $this->makeAdmin([]);
        $role = Role::findByName(PermissionSeeder::SUPER_ADMIN_ROLE, 'admin');
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hr.employee-loans.index'))
            ->assertOk();
    }

    public function test_employee_loan_product_not_on_public_website_catalog(): void
    {
        $product = LoanProduct::query()->where('code', 'EMPLOYEE_LOAN')->firstOrFail();
        $this->assertFalse($product->is_public_on_website);
        $this->assertTrue($product->isEmployeeLoanProduct());

        $public = app(PublicWebsiteCatalogService::class)->publicLoanProducts();
        $this->assertFalse($public->contains('code', 'EMPLOYEE_LOAN'));
    }

    public function test_employee_loan_product_excluded_from_customer_application_scope(): void
    {
        $this->assertTrue(
            LoanProduct::query()->forCustomerLoanApplication()->where('code', 'EMPLOYEE_LOAN')->doesntExist()
        );

        $admin = $this->makeAdmin(['loans.create']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.index'))
            ->assertOk()
            ->assertDontSee('Employee Loan');
    }

    public function test_employee_loan_product_hidden_on_customer_select_product_type(): void
    {
        $admin = $this->makeAdmin(['customers.create']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.select-product-type'))
            ->assertOk()
            ->assertDontSee('Employee Loan');
    }

    public function test_hr_permissions_do_not_include_loans_view_by_default(): void
    {
        $this->assertNotContains('loans.view', HrPermissions::all());
        $this->assertContains('hr.employee-loans.view', HrPermissions::all());
    }

    public function test_employee_rate_type_seeded_for_employee_product(): void
    {
        $product = LoanProduct::query()->where('code', 'EMPLOYEE_LOAN')->firstOrFail();
        $rateType = LoanRateType::query()->where('code', 'EMPLOYEE_RATE')->firstOrFail();

        $this->assertSame($product->id, $rateType->loan_product_id);
        $this->assertFalse($rateType->is_public_on_website);
    }
}
