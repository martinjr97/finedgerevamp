<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeContract;
use App\Models\EmployeeDependant;
use App\Models\EmployeeLeaveTransaction;
use App\Models\EmployeeNextOfKin;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\Hr\LeaveAccrualService;
use App\Services\Hr\LeaveApplicationService;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\LeaveDurationCalculator;
use App\Support\HrPermissions;
use Database\Seeders\HrSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(HrSeeder::class);
    }

    private function hrAdmin(array $extra = []): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'HR Co '.$suffix,
            'slug' => 'hr-co-'.$suffix,
            'code' => 'HR'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'HR',
            'last_name' => 'Manager',
            'email' => 'hr-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach (array_merge(HrPermissions::all(), $extra) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin->givePermissionTo(HrPermissions::all());

        return $admin;
    }

    public function test_hr_dashboard_and_employee_crud(): void
    {
        $admin = $this->hrAdmin();
        $dept = Department::query()->where('code', 'ICT')->firstOrFail();

        $this->actingAs($admin, 'admin')->get(route('admin.hr.dashboard'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.hr.employees.index'))->assertOk();

        $permanent = ContractType::query()->where('code', 'PERMANENT')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.hr.employees.store'), [
            'first_name' => 'Martin',
            'last_name' => 'Mwale',
            'employment_status' => 'active',
            'department_id' => $dept->id,
            'employee_number' => 'EMP-TEST-1',
            'contract_type_id' => $permanent->id,
            'contract_start_date' => now()->toDateString(),
            'basic_pay' => 8500,
            'compensation_effective_from' => now()->toDateString(),
            'payout_method' => 'none',
        ])->assertRedirect();

        $employee = Employee::query()->where('employee_number', 'EMP-TEST-1')->first();
        $this->assertNotNull($employee);
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-TEST-1', 'department_id' => $dept->id]);
        $this->assertDatabaseHas('employee_contracts', [
            'employee_id' => $employee->id,
            'contract_type_id' => $permanent->id,
            'status' => EmployeeContract::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('employee_compensations', [
            'employee_id' => $employee->id,
            'basic_pay' => 8500,
            'is_current' => true,
        ]);
    }

    public function test_employee_create_wizard_with_bank_details(): void
    {
        $admin = $this->hrAdmin();
        $permanent = ContractType::query()->where('code', 'PERMANENT')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.hr.employees.store'), [
            'first_name' => 'Bank',
            'last_name' => 'User',
            'employment_status' => 'active',
            'employee_number' => 'EMP-BANK-1',
            'contract_type_id' => $permanent->id,
            'contract_start_date' => '2026-01-01',
            'basic_pay' => 12000,
            'payout_method' => 'bank',
            'account_name' => 'Bank User',
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
        ])->assertRedirect();

        $employee = Employee::query()->where('employee_number', 'EMP-BANK-1')->firstOrFail();
        $this->assertDatabaseHas('employee_bank_accounts', [
            'employee_id' => $employee->id,
            'account_type' => 'bank',
            'account_number' => '1234567890',
            'is_primary' => true,
        ]);
    }

    public function test_employee_number_must_be_unique(): void
    {
        $admin = $this->hrAdmin();
        Employee::create(['first_name' => 'A', 'last_name' => 'One', 'employee_number' => 'EMP-DUP', 'employment_status' => 'active']);

        $permanent = ContractType::query()->where('code', 'PERMANENT')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.hr.employees.store'), [
            'first_name' => 'B',
            'last_name' => 'Two',
            'employment_status' => 'active',
            'employee_number' => 'EMP-DUP',
            'contract_type_id' => $permanent->id,
            'contract_start_date' => now()->toDateString(),
            'basic_pay' => 1000,
            'payout_method' => 'none',
        ])->assertSessionHasErrors('employee_number');
    }

    public function test_compensation_and_bank_permissions(): void
    {
        $viewer = $this->hrAdmin();
        $viewer->revokePermissionTo('hr.employee.compensation.view');
        $viewer->revokePermissionTo('hr.employee.bank.view');

        $employee = Employee::create(['first_name' => 'Pay', 'last_name' => 'Hidden', 'employment_status' => 'active']);
        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'basic_pay' => 5000,
            'effective_from' => now()->toDateString(),
            'is_current' => true,
            'currency' => 'ZMW',
            'status' => 'active',
        ]);

        $response = $this->actingAs($viewer, 'admin')
            ->get(route('admin.hr.employees.show', $employee));

        $response->assertOk()
            ->assertDontSee('Current basic pay (ZMW)', false)
            ->assertDontSee('Update basic pay', false);
    }

    public function test_department_head_and_counts(): void
    {
        $admin = $this->hrAdmin();
        $head = Employee::create(['first_name' => 'Head', 'last_name' => 'Person', 'employment_status' => 'active']);
        $dept = Department::query()->where('code', 'ICT')->firstOrFail();
        $dept->update(['head_employee_id' => $head->id]);
        Employee::create(['first_name' => 'Staff', 'last_name' => 'One', 'employment_status' => 'active', 'department_id' => $dept->id]);

        $this->actingAs($admin, 'admin')->get(route('admin.hr.departments.show', $dept))->assertOk()->assertSee('Head Person');
    }

    public function test_contract_type_and_contract_history(): void
    {
        $admin = $this->hrAdmin();
        $employee = Employee::create(['first_name' => 'Contract', 'last_name' => 'Worker', 'employment_status' => 'active']);
        $permanent = ContractType::query()->where('code', 'PERMANENT')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.hr.contracts.store'), [
            'employee_id' => $employee->id,
            'contract_type_id' => $permanent->id,
            'start_date' => now()->subYear()->toDateString(),
            'status' => 'renewed',
        ])->assertRedirect();

        $this->actingAs($admin, 'admin')->post(route('admin.hr.contracts.store'), [
            'employee_id' => $employee->id,
            'contract_type_id' => $permanent->id,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ])->assertRedirect();

        $this->assertSame(2, EmployeeContract::query()->where('employee_id', $employee->id)->count());
        $this->assertSame(1, EmployeeContract::query()->where('employee_id', $employee->id)->where('status', 'active')->count());
    }

    public function test_expiring_contract_scope(): void
    {
        $employee = Employee::create(['first_name' => 'Exp', 'last_name' => 'Soon', 'employment_status' => 'active']);
        $fixed = ContractType::query()->where('code', 'FIXED_TERM')->firstOrFail();

        EmployeeContract::create([
            'employee_id' => $employee->id,
            'contract_type_id' => $fixed->id,
            'start_date' => now()->subMonths(11),
            'end_date' => now()->addDays(20),
            'status' => EmployeeContract::STATUS_ACTIVE,
        ]);

        $this->assertGreaterThan(0, EmployeeContract::query()->expiringWithin(30)->count());
    }

    public function test_leave_approval_and_balance(): void
    {
        $admin = $this->hrAdmin();
        $employee = Employee::create(['first_name' => 'Leave', 'last_name' => 'User', 'employment_status' => 'active']);
        $annual = LeaveType::query()->where('code', 'annual')->firstOrFail();

        EmployeeLeaveTransaction::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'transaction_date' => now()->toDateString(),
            'type' => EmployeeLeaveTransaction::TYPE_ACCRUAL,
            'days' => 10,
        ]);

        $app = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'days_requested' => 2,
            'status' => LeaveApplication::STATUS_PENDING,
        ]);

        app(LeaveApplicationService::class)->approve($app, $admin->id);
        $balance = app(LeaveBalanceService::class)->balanceForType($employee->id, $annual->id);
        $this->assertSame(8.0, $balance['available']);

        $rejectApp = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'days_requested' => 1,
            'status' => LeaveApplication::STATUS_PENDING,
        ]);
        app(LeaveApplicationService::class)->reject($rejectApp, $admin->id);
        $this->assertSame(LeaveApplication::STATUS_REJECTED, $rejectApp->fresh()->status);
    }

    public function test_monthly_accrual_is_idempotent(): void
    {
        $employee = Employee::create(['first_name' => 'Accrual', 'last_name' => 'User', 'employment_status' => 'active']);
        $permanent = ContractType::query()->where('code', 'PERMANENT')->firstOrFail();
        EmployeeContract::create([
            'employee_id' => $employee->id,
            'contract_type_id' => $permanent->id,
            'start_date' => now()->startOfMonth(),
            'status' => EmployeeContract::STATUS_ACTIVE,
        ]);

        $service = app(LeaveAccrualService::class);
        $first = $service->accrueForMonth(now());
        $second = $service->accrueForMonth(now());

        $this->assertGreaterThan(0, $first['processed']);
        $this->assertSame(0, $second['processed']);
    }

    public function test_kin_and_dependants(): void
    {
        $admin = $this->hrAdmin();
        $employee = Employee::create(['first_name' => 'Family', 'last_name' => 'Member', 'employment_status' => 'active']);

        $this->actingAs($admin, 'admin')->post(route('admin.hr.employees.next-of-kin.store', $employee), [
            'full_name' => 'Spouse Name',
            'relationship' => 'Spouse',
            'is_primary' => 1,
        ])->assertRedirect();

        $this->actingAs($admin, 'admin')->post(route('admin.hr.employees.dependants.store', $employee), [
            'full_name' => 'Child Name',
            'relationship' => 'Child',
        ])->assertRedirect();

        $this->assertSame(1, EmployeeNextOfKin::query()->where('employee_id', $employee->id)->count());
        $this->assertSame(1, EmployeeDependant::query()->where('employee_id', $employee->id)->count());
    }

    public function test_leave_duration_calculator_uses_inclusive_calendar_days(): void
    {
        $days = app(LeaveDurationCalculator::class)->calculateDays('2026-01-01', '2026-01-03');
        $this->assertSame(3.0, $days);
    }

    public function test_leave_applications_index_shows_only_pending(): void
    {
        $admin = $this->hrAdmin();
        $pendingEmployee = Employee::create(['first_name' => 'Wait', 'last_name' => 'Pending', 'employment_status' => 'active']);
        $doneEmployee = Employee::create(['first_name' => 'Already', 'last_name' => 'Approved', 'employment_status' => 'active']);
        $annual = LeaveType::query()->where('code', 'annual')->firstOrFail();

        LeaveApplication::create([
            'employee_id' => $pendingEmployee->id,
            'leave_type_id' => $annual->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'days_requested' => 2,
            'status' => LeaveApplication::STATUS_PENDING,
        ]);
        LeaveApplication::create([
            'employee_id' => $doneEmployee->id,
            'leave_type_id' => $annual->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->subMonth(),
            'days_requested' => 1,
            'status' => LeaveApplication::STATUS_APPROVED,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hr.leave.applications.index'))
            ->assertOk()
            ->assertSee('Pending Leave Applications')
            ->assertSee('Wait Pending')
            ->assertDontSee('Already Approved');
    }

    public function test_leave_store_rejects_insufficient_balance(): void
    {
        $admin = $this->hrAdmin();
        $employee = Employee::create(['first_name' => 'Low', 'last_name' => 'Balance', 'employment_status' => 'active']);
        $annual = LeaveType::query()->where('code', 'annual')->firstOrFail();

        EmployeeLeaveTransaction::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'transaction_date' => now()->toDateString(),
            'type' => EmployeeLeaveTransaction::TYPE_ACCRUAL,
            'days' => 1,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.hr.leave.applications.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ])->assertSessionHasErrors('end_date');

        $this->assertSame(0, LeaveApplication::query()->where('employee_id', $employee->id)->count());
    }

    public function test_leave_store_can_auto_approve_when_permitted(): void
    {
        $admin = $this->hrAdmin();
        $employee = Employee::create(['first_name' => 'Auto', 'last_name' => 'Approve', 'employment_status' => 'active']);
        $annual = LeaveType::query()->where('code', 'annual')->firstOrFail();

        EmployeeLeaveTransaction::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'transaction_date' => now()->toDateString(),
            'type' => EmployeeLeaveTransaction::TYPE_ACCRUAL,
            'days' => 5,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.hr.leave.applications.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'approve_now' => '1',
        ])->assertRedirect(route('admin.hr.leave.history.index'));

        $this->assertDatabaseHas('leave_applications', [
            'employee_id' => $employee->id,
            'status' => LeaveApplication::STATUS_APPROVED,
        ]);
    }

    public function test_position_can_be_created_via_json_for_wizard(): void
    {
        $admin = $this->hrAdmin();
        $dept = Department::query()->where('code', 'HR')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.hr.positions.store'), [
            'name' => 'Payroll Officer',
            'department_id' => $dept->id,
        ]);

        $response->assertCreated()->assertJsonPath('position.name', 'Payroll Officer');
        $this->assertDatabaseHas('positions', ['name' => 'Payroll Officer', 'department_id' => $dept->id]);
    }

    public function test_unauthorized_user_blocked_from_hr(): void
    {
        $suffix = Str::lower(Str::random(4));
        $company = Company::create([
            'name' => 'No HR '.$suffix,
            'slug' => 'no-hr-'.$suffix,
            'code' => 'NH'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Basic',
            'last_name' => 'User',
            'email' => 'basic-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin')->get(route('admin.hr.dashboard'))->assertForbidden();
    }
}
