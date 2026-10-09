<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\FinancialTransaction;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\Hr\EmployeeLoans\EmployeeLoanDisbursementService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanPricingService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanService;
use Database\Seeders\CompanySeeder;
use Database\Seeders\EmployeeLoanProductSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeLoanIntegrationTest extends TestCase
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

    private function adminWith(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $admin = Admin::create([
            'company_id' => Company::first()->id,
            'first_name' => 'Int',
            'last_name' => 'Test',
            'email' => "int-{$suffix}@example.com",
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
            $admin->givePermissionTo($p);
        }

        return $admin;
    }

    private function employeeRate(): LoanRate
    {
        $type = LoanRateType::query()->where('code', 'EMPLOYEE_RATE')->firstOrFail();

        return LoanRate::query()->create([
            'loan_rate_type_id' => $type->id,
            'tenure_months' => 6,
            'term_interest_percentage' => 10,
            'processing_fee_percentage' => 0,
            'arrear_rate' => 0,
            'is_active' => true,
        ]);
    }

    /** @return array{loan: EmployeeLoan, bank: Bank} */
    private function approvedLoanReadyToDisburse(): array
    {
        $rate = $this->employeeRate();
        $employee = Employee::create(['first_name' => 'Fin', 'last_name' => 'Test', 'employment_status' => 'active']);
        $creator = $this->adminWith(['hr.employee-loans.create', 'hr.employee-loans.update']);
        $approver = $this->adminWith(['hr.employee-loans.approve']);

        $service = app(EmployeeLoanService::class);
        $loan = $service->createDraft([
            'employee_id' => $employee->id,
            'loan_rate_id' => $rate->id,
            'principal_amount' => 10000,
            'tenure_months' => 6,
            'repayment_frequency' => 'monthly',
            'first_payment_date' => now()->addMonth()->toDateString(),
        ], $creator);
        $service->submitForApproval($loan);
        $loan = $service->approve($loan->fresh(), $approver);

        $bank = Bank::create([
            'name' => 'Treasury Bank',
            'bank_name' => 'Treasury',
            'account_name' => 'Main',
            'account_number' => '999',
            'current_balance' => 100000,
            'is_active' => true,
        ]);

        return ['loan' => $loan, 'bank' => $bank];
    }

    public function test_schedule_total_matches_quoted_total_within_tolerance(): void
    {
        ['loan' => $loan] = $this->approvedLoanReadyToDisburse();

        $scheduleSum = (float) $loan->paymentSchedules()->sum('expected_amount');
        $quotedTotal = (float) $loan->total_amount;

        $this->assertLessThanOrEqual(0.05, abs($scheduleSum - $quotedTotal));
    }

    public function test_duplicate_disbursement_blocked_and_single_financial_transaction(): void
    {
        ['loan' => $loan, 'bank' => $bank] = $this->approvedLoanReadyToDisburse();
        $disburser = $this->adminWith(['hr.employee-loans.disburse']);
        $this->actingAs($disburser, 'admin');

        $disburse = app(EmployeeLoanDisbursementService::class);
        $disburse->disburse($loan, $disburser, 'bank', $bank->id);

        try {
            $disburse->disburse($loan->fresh(), $disburser, 'bank', $bank->id);
            $this->fail('Expected duplicate disbursement to be rejected.');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertSame(1, FinancialTransaction::query()->where('category', 'employee_loan_disbursement')->count());
    }

    public function test_employee_deactivation_does_not_settle_or_delete_loan(): void
    {
        ['loan' => $loan, 'bank' => $bank] = $this->approvedLoanReadyToDisburse();
        $disburser = $this->adminWith(['hr.employee-loans.disburse']);
        $this->actingAs($disburser, 'admin');
        app(EmployeeLoanDisbursementService::class)->disburse($loan, $disburser, 'bank', $bank->id);

        $employee = $loan->employee;
        $employee->update(['employment_status' => 'terminated']);

        $fresh = $loan->fresh();
        $this->assertSame(EmployeeLoan::STATUS_ACTIVE, $fresh->status);
        $this->assertGreaterThan(0, (float) $fresh->outstanding_balance);
        $this->assertDatabaseHas('employee_loans', ['id' => $loan->id, 'status' => EmployeeLoan::STATUS_ACTIVE]);
    }

    public function test_balance_sheet_includes_employee_loans_receivable(): void
    {
        ['loan' => $loan, 'bank' => $bank] = $this->approvedLoanReadyToDisburse();
        $disburser = $this->adminWith(['hr.employee-loans.disburse', 'financial-statements.view']);
        $this->actingAs($disburser, 'admin');
        app(EmployeeLoanDisbursementService::class)->disburse($loan, $disburser, 'bank', $bank->id);

        $outstanding = (float) $loan->fresh()->outstanding_balance;

        $response = $this->get(route('admin.financial-statements.balance-sheet'));
        $response->assertOk();
        $response->assertViewHas('employeeLoansReceivable', fn ($value) => abs((float) $value - $outstanding) < 0.01);
        $response->assertViewHas('customerLoansReceivable');
    }

    public function test_employee_profile_loans_tab_requires_employee_loans_view(): void
    {
        $employee = Employee::create(['first_name' => 'Prof', 'last_name' => 'Emp', 'employment_status' => 'active']);

        $hrOnly = $this->adminWith(['hr.employees.view']);
        $this->actingAs($hrOnly, 'admin')
            ->get(route('admin.hr.employees.show', $employee))
            ->assertOk()
            ->assertDontSee('Employee Loans');

        $withLoans = $this->adminWith(['hr.employees.view', 'hr.employee-loans.view']);
        $this->actingAs($withLoans, 'admin')
            ->get(route('admin.hr.employees.show', $employee))
            ->assertOk()
            ->assertSee('Employee Loans');
    }

    public function test_pricing_preview_does_not_create_loan_record(): void
    {
        $rate = $this->employeeRate();
        $employee = Employee::create(['first_name' => 'Prev', 'last_name' => 'Only', 'employment_status' => 'active']);
        $admin = $this->adminWith(['hr.employee-loans.create']);

        $before = EmployeeLoan::query()->count();

        $preview = app(EmployeeLoanPricingService::class)->preview([
            'loan_rate_id' => $rate->id,
            'principal' => 5000,
            'tenure_months' => 6,
            'repayment_frequency' => 'monthly',
            'first_payment_date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertArrayHasKey('installments', $preview);
        $this->assertNotEmpty($preview['installments']);
        $this->assertSame($before, EmployeeLoan::query()->count());
        $this->assertDatabaseMissing('employee_loans', ['employee_id' => $employee->id]);
    }
}
