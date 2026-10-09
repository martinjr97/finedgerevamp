<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeContract;
use App\Models\EmployeeDependant;
use App\Models\EmployeeNextOfKin;
use App\Models\FinancialInstitution;
use App\Models\Position;
use App\Models\WalletProvider;
use App\Services\Hr\EmployeeCompensationService;
use App\Services\Hr\EmployeeContractService;
use App\Services\Hr\LeaveBalanceService;
use App\Support\EmployeeTitles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.view'), 403);

        $query = Employee::query()
            ->with(['hrDepartment', 'position', 'activeContract.contractType', 'currentCompensation']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->string('employment_status'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $employees = $query->orderBy('first_name')->orderBy('last_name')->paginate(20)->withQueryString();
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.hr.employees.index', compact('employees', 'departments'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.create'), 403);

        $contractTypes = ContractType::query()->where('is_active', true)->orderBy('name')->get();
        $contractTypesForWizard = $contractTypes->map(fn (ContractType $type) => [
            'id' => $type->id,
            'name' => $type->name,
            'is_permanent' => (bool) $type->is_permanent,
            'has_end_date' => (bool) $type->has_end_date,
            'default_duration_months' => $type->default_duration_months,
        ])->values()->all();

        $positions = Position::query()->where('is_active', true)->with('department:id,name')->orderBy('name')->get();

        return view('admin.hr.employees.create', [
            ...$this->formOptions(),
            'contractTypes' => $contractTypes,
            'contractTypesForWizard' => $contractTypesForWizard,
            'mobileMoneyProviders' => $this->mobileMoneyProviderNames(),
            'employeeTitles' => EmployeeTitles::all(),
            'positionsForWizard' => $positions->map(fn (Position $position) => [
                'id' => $position->id,
                'name' => $position->name,
                'department_id' => $position->department_id,
                'department_name' => $position->department?->name,
            ])->values()->all(),
            'canManagePositions' => auth('admin')->user()?->can('hr.positions.manage') ?? false,
            'positionStoreUrl' => route('admin.hr.positions.store'),
        ]);
    }

    public function store(
        Request $request,
        EmployeeCompensationService $compensationService,
        EmployeeContractService $contractService,
    ): RedirectResponse {
        abort_unless(auth('admin')->user()?->can('hr.employees.create'), 403);

        $canManageCompensation = auth('admin')->user()?->can('hr.employee.compensation.manage') ?? false;
        $canManageBank = auth('admin')->user()?->can('hr.employee.bank.manage') ?? false;

        $employeeRules = $this->validateEmployeeRules(null);
        $wizardRules = [
            'contract_type_id' => ['required', 'integer', 'exists:contract_types,id'],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'compensation_effective_from' => ['nullable', 'date'],
            'payout_method' => ['nullable', Rule::in(['bank', 'mobile_money', 'none'])],
            'mobile_money_provider' => ['nullable', 'string', 'max:100'],
            'mobile_account_name' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
        ];

        if ($canManageCompensation) {
            $wizardRules['basic_pay'] = ['required', 'numeric', 'min:0'];
        } else {
            $wizardRules['basic_pay'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate(array_merge($employeeRules, $wizardRules));

        $contractType = ContractType::query()->findOrFail($validated['contract_type_id']);
        if ($contractType->has_end_date && ! $contractType->is_permanent && empty($validated['contract_end_date'])) {
            return back()->withInput()->withErrors(['contract_end_date' => 'Contract end date is required for this contract type.']);
        }

        $payoutMethod = $canManageBank ? ($validated['payout_method'] ?? 'none') : 'none';
        if ($payoutMethod === 'bank') {
            $request->validate([
                'account_name' => ['required', 'string', 'max:255'],
                'account_number' => ['required', 'string', 'max:50'],
            ]);
        } elseif ($payoutMethod === 'mobile_money') {
            $request->validate([
                'mobile_money_provider' => ['required', 'string', 'max:100'],
                'mobile_account_name' => ['required', 'string', 'max:255'],
                'mobile_number' => ['required', 'string', 'max:50'],
            ]);
        }

        $employeeNumber = trim((string) ($validated['employee_number'] ?? ''));
        if ($employeeNumber === '') {
            $employeeNumber = Employee::generateEmployeeNumber();
        }

        $employeePayload = collect($validated)->only(array_keys($employeeRules))->all();
        $employeePayload['employee_number'] = $employeeNumber;
        $employeePayload['employment_start_date'] = $validated['date_joined'] ?? $validated['contract_start_date'];

        try {
            $employee = DB::transaction(function () use (
                $validated,
                $employeePayload,
                $contractType,
                $compensationService,
                $contractService,
                $canManageCompensation,
                $canManageBank,
                $payoutMethod,
            ) {
                $employee = Employee::create($employeePayload);

                $basicPay = isset($validated['basic_pay']) ? (float) $validated['basic_pay'] : null;

                $contract = EmployeeContract::create([
                    'employee_id' => $employee->id,
                    'contract_type_id' => $contractType->id,
                    'contract_number' => 'CON-'.$employee->employee_number,
                    'start_date' => $validated['contract_start_date'],
                    'end_date' => $contractType->is_permanent ? null : ($validated['contract_end_date'] ?? null),
                    'basic_pay_snapshot' => $basicPay,
                    'status' => EmployeeContract::STATUS_DRAFT,
                    'created_by' => auth('admin')->id(),
                ]);

                $contractService->activate($contract, auth('admin')->id());

                if ($canManageCompensation && $basicPay !== null) {
                    $compensationService->record(
                        $employee,
                        $basicPay,
                        $validated['compensation_effective_from'] ?? $validated['contract_start_date'],
                        auth('admin')->id(),
                    );
                }

                if ($canManageBank && $payoutMethod === 'bank') {
                    EmployeeBankAccount::create([
                        'employee_id' => $employee->id,
                        'financial_institution_id' => $validated['financial_institution_id'] ?? null,
                        'bank_name' => $validated['bank_name'] ?? null,
                        'branch_name' => $validated['branch_name'] ?? null,
                        'branch_code' => $validated['branch_code'] ?? null,
                        'account_name' => $validated['account_name'],
                        'account_number' => $validated['account_number'],
                        'account_type' => 'bank',
                        'currency' => 'ZMW',
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                } elseif ($canManageBank && $payoutMethod === 'mobile_money') {
                    EmployeeBankAccount::create([
                        'employee_id' => $employee->id,
                        'bank_name' => $validated['mobile_money_provider'],
                        'account_name' => $validated['mobile_account_name'],
                        'account_number' => $validated['mobile_number'],
                        'account_type' => 'mobile_money',
                        'currency' => 'ZMW',
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                }

                return $employee;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create employee: '.$e->getMessage());
        }

        return redirect()->route('admin.hr.employees.show', $employee)
            ->with('status', 'Employee created with contract and payment details.');
    }

    public function show(Employee $employee, LeaveBalanceService $leaveBalanceService): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.view'), 403);

        $employee->load([
            'hrDepartment',
            'position',
            'branch',
            'supervisor',
            'activeContract.contractType',
            'contracts.contractType',
            'currentCompensation',
            'compensations',
            'bankAccounts.financialInstitution',
            'nextOfKin',
            'dependants',
            'leaveApplications.leaveType',
            'documents',
        ]);

        $canViewCompensation = auth('admin')->user()?->can('hr.employee.compensation.view') ?? false;
        $canViewBank = auth('admin')->user()?->can('hr.employee.bank.view') ?? false;
        $canViewEmployeeLoans = auth('admin')->user()?->can('hr.employee-loans.view') ?? false;
        $leaveBalances = $leaveBalanceService->balancesForEmployee($employee);

        if ($canViewEmployeeLoans) {
            $employee->load(['employeeLoans' => fn ($q) => $q->latest('id')->limit(10)]);
        }

        return view('admin.hr.employees.show', compact(
            'employee',
            'canViewCompensation',
            'canViewBank',
            'canViewEmployeeLoans',
            'leaveBalances',
        ) + $this->formOptions() + [
            'mobileMoneyProviders' => $this->mobileMoneyProviderNames(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.update'), 403);

        return view('admin.hr.employees.edit', ['employee' => $employee] + $this->formOptions());
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.update'), 403);

        $employee->update($this->validateEmployee($request, $employee->id));

        return redirect()->route('admin.hr.employees.show', $employee)
            ->with('status', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.delete'), 403);

        $employee->update(['employment_status' => 'terminated', 'is_active' => false]);
        $employee->delete();

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee archived successfully.');
    }

    public function storeCompensation(Request $request, Employee $employee, EmployeeCompensationService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.employee.compensation.manage'), 403);

        $validated = $request->validate([
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $service->record(
            $employee,
            (float) $validated['basic_pay'],
            $validated['effective_from'],
            auth('admin')->id(),
            $validated['currency'] ?? 'ZMW',
        );

        return back()
            ->with('status', 'Compensation recorded.')
            ->with('hr_employee_tab', 'compensation');
    }

    public function storeBankAccount(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.employee.bank.manage'), 403);

        $payoutMethod = $request->input('payout_method', 'bank');

        if ($payoutMethod === 'mobile_money') {
            $validated = $request->validate([
                'mobile_money_provider' => ['required', 'string', 'max:100'],
                'mobile_account_name' => ['required', 'string', 'max:255'],
                'mobile_number' => ['required', 'string', 'max:50'],
                'is_primary' => ['nullable', 'boolean'],
            ]);

            if ($request->boolean('is_primary')) {
                EmployeeBankAccount::query()->where('employee_id', $employee->id)->update(['is_primary' => false]);
            }

            EmployeeBankAccount::create([
                'employee_id' => $employee->id,
                'bank_name' => $validated['mobile_money_provider'],
                'account_name' => $validated['mobile_account_name'],
                'account_number' => $validated['mobile_number'],
                'account_type' => 'mobile_money',
                'currency' => 'ZMW',
                'is_primary' => $request->boolean('is_primary'),
                'is_active' => true,
            ]);
        } else {
            $validated = $request->validate([
                'financial_institution_id' => ['nullable', 'integer', 'exists:financial_institutions,id'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'branch_name' => ['nullable', 'string', 'max:255'],
                'branch_code' => ['nullable', 'string', 'max:50'],
                'account_name' => ['required', 'string', 'max:255'],
                'account_number' => ['required', 'string', 'max:50'],
                'account_type' => ['nullable', 'string', 'max:30'],
                'currency' => ['nullable', 'string', 'size:3'],
                'is_primary' => ['nullable', 'boolean'],
            ]);

            if ($request->boolean('is_primary')) {
                EmployeeBankAccount::query()->where('employee_id', $employee->id)->update(['is_primary' => false]);
            }

            EmployeeBankAccount::create([
                ...$validated,
                'employee_id' => $employee->id,
                'account_type' => $validated['account_type'] ?? 'bank',
                'currency' => $validated['currency'] ?? 'ZMW',
                'is_primary' => $request->boolean('is_primary'),
                'is_active' => true,
            ]);
        }

        return back()
            ->with('status', 'Payment account added.')
            ->with('hr_employee_tab', 'bank');
    }

    public function storeNextOfKin(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.update'), 403);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'alternative_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_primary')) {
            EmployeeNextOfKin::query()->where('employee_id', $employee->id)->update(['is_primary' => false]);
        }

        EmployeeNextOfKin::create([...$validated, 'employee_id' => $employee->id, 'is_primary' => $request->boolean('is_primary')]);

        return back()
            ->with('status', 'Next of kin added.')
            ->with('hr_employee_tab', 'kin');
    }

    public function storeDependant(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.employees.update'), 403);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        EmployeeDependant::create([...$validated, 'employee_id' => $employee->id, 'is_active' => true]);

        return back()
            ->with('status', 'Dependant added.')
            ->with('hr_employee_tab', 'dependants');
    }

    /**
     * @return list<string>
     */
    private function mobileMoneyProviderNames(): array
    {
        $walletProviders = WalletProvider::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

        return $walletProviders !== []
            ? $walletProviders
            : ['MTN Mobile Money', 'Airtel Money', 'Zamtel Kwacha'];
    }

    private function formOptions(): array
    {
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(),
            'supervisors' => Employee::query()->active()->orderBy('first_name')->get(),
            'financialInstitutions' => FinancialInstitution::query()->active()->orderBy('name')->get(),
            'employmentStatuses' => ['active', 'inactive', 'suspended', 'terminated', 'retired', 'deceased'],
            'employeeTitles' => EmployeeTitles::all(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?int $employeeId = null): array
    {
        return $request->validate($this->validateEmployeeRules($employeeId));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployeeRules(?int $employeeId = null): array
    {
        return [
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:employees,employee_number,'.$employeeId],
            'title' => ['nullable', 'string', Rule::in(EmployeeTitles::all())],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternative_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'residential_address' => ['nullable', 'string'],
            'postal_address' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'reports_to_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date_joined' => ['nullable', 'date'],
            'employment_start_date' => ['nullable', 'date'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'employment_status' => ['required', 'in:active,inactive,suspended,terminated,retired,deceased'],
            'financial_institution_id' => ['nullable', 'integer', 'exists:financial_institutions,id'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
