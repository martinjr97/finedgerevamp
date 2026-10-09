<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Loan;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\Hr\EmployeeLoans\EmployeeLoanDisbursementService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanPricingService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanRepaymentService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanService;
use Database\Seeders\CompanySeeder;
use Database\Seeders\EmployeeLoanProductSeeder;
use Database\Seeders\HrSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeLoanWorkflowTest extends TestCase
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
        $company = Company::first();
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'EL',
            'last_name' => 'User',
            'email' => "el-{$suffix}@example.com",
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

    private function employeeRate(float $termPercent = 10.0, int $tenure = 6): LoanRate
    {
        $type = LoanRateType::query()->where('code', 'EMPLOYEE_RATE')->firstOrFail();

        return LoanRate::query()->create([
            'loan_rate_type_id' => $type->id,
            'tenure_months' => $tenure,
            'term_interest_percentage' => $termPercent,
            'processing_fee_percentage' => 0,
            'arrear_rate' => 0,
            'is_active' => true,
        ]);
    }

    public function test_rate_snapshot_preserved_when_employee_rate_changes(): void
    {
        $rate = $this->employeeRate(10.0);
        $employee = Employee::create(['first_name' => 'Snap', 'last_name' => 'Test', 'employment_status' => 'active']);
        $creator = $this->adminWith(['hr.employee-loans.create', 'hr.employee-loans.approve', 'hr.employee-loans.update']);
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
        $service->approve($loan->fresh(), $approver);

        $this->assertSame('10.0000', (string) $loan->fresh()->quoted_term_rate);

        $rate->update(['term_interest_percentage' => 12.0]);

        $loan2 = $service->createDraft([
            'employee_id' => $employee->id,
            'loan_rate_id' => $rate->id,
            'principal_amount' => 10000,
            'tenure_months' => 6,
            'repayment_frequency' => 'monthly',
            'first_payment_date' => now()->addMonth()->toDateString(),
        ], $creator);

        $this->assertSame('12.0000', (string) $loan2->quoted_term_rate);
        $this->assertSame('10.0000', (string) $loan->fresh()->quoted_term_rate);
    }

    public function test_creator_cannot_approve_own_loan(): void
    {
        $rate = $this->employeeRate();
        $employee = Employee::create(['first_name' => 'MK', 'last_name' => 'Test', 'employment_status' => 'active']);
        $creator = $this->adminWith(['hr.employee-loans.create', 'hr.employee-loans.approve', 'hr.employee-loans.update']);
        $service = app(EmployeeLoanService::class);
        $loan = $service->createDraft([
            'employee_id' => $employee->id,
            'loan_rate_id' => $rate->id,
            'principal_amount' => 5000,
            'tenure_months' => 6,
            'repayment_frequency' => 'monthly',
            'first_payment_date' => now()->addMonth()->toDateString(),
        ], $creator);
        $service->submitForApproval($loan);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->approve($loan->fresh(), $creator);
    }

    public function test_full_workflow_disburse_and_repay(): void
    {
        $rate = $this->employeeRate(10.0);
        $employee = Employee::create(['first_name' => 'Flow', 'last_name' => 'Test', 'employment_status' => 'active']);
        $creator = $this->adminWith(['hr.employee-loans.create', 'hr.employee-loans.update']);
        $approver = $this->adminWith(['hr.employee-loans.approve']);
        $disburser = $this->adminWith(['hr.employee-loans.disburse', 'hr.employee-loans.repay']);

        $bank = Bank::create([
            'name' => 'Test Bank',
            'bank_name' => 'Test',
            'account_name' => 'Treasury',
            'account_number' => '123',
            'current_balance' => 50000,
            'is_active' => true,
        ]);
        $balanceBefore = (float) $bank->current_balance;

        $loanService = app(EmployeeLoanService::class);
        $loan = $loanService->createDraft([
            'employee_id' => $employee->id,
            'loan_rate_id' => $rate->id,
            'principal_amount' => 10000,
            'tenure_months' => 6,
            'repayment_frequency' => 'monthly',
            'first_payment_date' => now()->addMonth()->toDateString(),
        ], $creator);
        $loanService->submitForApproval($loan);
        $loanService->approve($loan->fresh(), $approver);

        $this->assertSame(6, $loan->fresh()->paymentSchedules()->count());
        $scheduleTotal = (float) $loan->fresh()->paymentSchedules()->sum('expected_amount');
        $this->assertEqualsWithDelta((float) $loan->fresh()->total_amount, $scheduleTotal, 0.05);

        app(EmployeeLoanDisbursementService::class)->disburse($loan->fresh(), $disburser, 'bank', $bank->id);
        $loan = $loan->fresh();
        $this->assertSame(EmployeeLoan::STATUS_ACTIVE, $loan->status);
        $this->assertSame('completed', $loan->disbursement_status);
        $this->assertEqualsWithDelta($balanceBefore - 10000, (float) $bank->fresh()->current_balance, 0.01);

        $portfolioBefore = Loan::query()->activePortfolio()->count();

        app(EmployeeLoanRepaymentService::class)->recordRepayment($loan, [
            'amount' => 500,
            'effective_date' => now()->toDateString(),
            'repayment_source' => 'manual',
            'received_via_type' => 'bank',
            'received_via_id' => $bank->id,
        ], $disburser);

        $this->assertSame($portfolioBefore, Loan::query()->activePortfolio()->count());
        $this->assertDatabaseHas('employee_loan_repayments', ['employee_loan_id' => $loan->id, 'status' => 'completed']);
    }

    public function test_non_employee_rate_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $characterProduct = \App\Models\LoanProduct::create([
            'company_id' => Company::first()->id,
            'name' => 'Char',
            'code' => 'CHAR-X',
            'category' => 'character',
            'is_active' => true,
        ]);
        $type = LoanRateType::create([
            'loan_product_id' => $characterProduct->id,
            'name' => 'Not Employee',
            'code' => 'NOT_EMP',
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);
        $badRate = LoanRate::create([
            'loan_rate_type_id' => $type->id,
            'tenure_months' => 3,
            'term_interest_percentage' => 5,
            'processing_fee_percentage' => 0,
            'arrear_rate' => 0,
            'is_active' => true,
        ]);

        app(EmployeeLoanPricingService::class)->quote([
            'loan_rate_id' => $badRate->id,
            'principal' => 1000,
            'tenure_months' => 3,
        ]);
    }

    public function test_pricing_preview_does_not_persist_loan(): void
    {
        $admin = $this->adminWith(['hr.employee-loans.create']);
        $rate = $this->employeeRate();
        $before = EmployeeLoan::count();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.hr.employee-loans.pricing-preview', [
                'loan_rate_id' => $rate->id,
                'principal_amount' => 8000,
                'tenure_months' => 6,
            ]))
            ->assertOk()
            ->assertJsonStructure(['total_repayable', 'installment_count']);

        $this->assertSame($before, EmployeeLoan::count());
    }
}
