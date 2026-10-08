<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\Hr\EmployeeContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeContractController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contracts.view'), 403);

        $query = EmployeeContract::query()->with(['employee', 'contractType']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('contract_type_id')) {
            $query->where('contract_type_id', $request->integer('contract_type_id'));
        }
        if ($request->boolean('expiring_soon')) {
            $query->expiringWithin(90);
        }

        $contracts = $query->orderByDesc('start_date')->paginate(25)->withQueryString();
        $contractTypes = ContractType::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.hr.contracts.index', compact('contracts', 'contractTypes'));
    }

    public function create(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contracts.manage'), 403);

        return view('admin.hr.contracts.create', [
            'employees' => Employee::query()->active()->orderBy('first_name')->get(),
            'contractTypes' => ContractType::query()->where('is_active', true)->get(),
            'preselectedEmployeeId' => $request->integer('employee_id'),
        ]);
    }

    public function store(Request $request, EmployeeContractService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.contracts.manage'), 403);

        $validated = $this->validateContract($request);
        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $type = ContractType::query()->findOrFail($validated['contract_type_id']);

        if ($type->has_end_date && empty($validated['end_date'])) {
            return back()->withInput()->withErrors(['end_date' => 'End date is required for this contract type.']);
        }
        if ($type->is_permanent) {
            $validated['end_date'] = null;
        }

        $contract = EmployeeContract::create([
            ...$validated,
            'created_by' => auth('admin')->id(),
            'status' => $request->input('status', EmployeeContract::STATUS_DRAFT),
        ]);

        if ($contract->status === EmployeeContract::STATUS_ACTIVE) {
            $service->activate($contract, auth('admin')->id());
        }

        return redirect()->route('admin.hr.contracts.index')->with('status', 'Contract recorded.');
    }

    public function show(EmployeeContract $contract): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contracts.view'), 403);

        $contract->load(['employee', 'contractType']);

        return view('admin.hr.contracts.show', compact('contract'));
    }

    public function activate(EmployeeContract $contract, EmployeeContractService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.contracts.manage'), 403);

        $service->activate($contract, auth('admin')->id());

        return back()->with('status', 'Contract activated.');
    }

    private function validateContract(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'contract_type_id' => ['required', 'integer', 'exists:contract_types,id'],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'basic_pay_snapshot' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,active,expired,terminated,renewed'],
            'probation_end_date' => ['nullable', 'date'],
            'signed_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
