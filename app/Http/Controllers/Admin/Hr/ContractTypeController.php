<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use App\Models\ContractTypeLeaveRule;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractTypeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contract-types.view'), 403);

        $contractTypes = ContractType::query()->with('leaveRules.leaveType')->orderBy('name')->get();

        return view('admin.hr.contract-types.index', compact('contractTypes'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contract-types.manage'), 403);

        return view('admin.hr.contract-types.create', [
            'contractType' => null,
            'leaveTypes' => $this->activeLeaveTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.contract-types.manage'), 403);

        $validated = $this->validateType($request);
        $this->validateLeaveRules($request);
        $type = ContractType::create($validated);
        $this->syncLeaveRules($request, $type);

        return redirect()->route('admin.hr.contract-types.index')->with('status', 'Contract type created.');
    }

    public function show(ContractType $contractType): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contract-types.view'), 403);

        $contractType->load(['leaveRules.leaveType']);
        $contractsCount = $contractType->contracts()->count();

        return view('admin.hr.contract-types.show', compact('contractType', 'contractsCount'));
    }

    public function edit(ContractType $contractType): View
    {
        abort_unless(auth('admin')->user()?->can('hr.contract-types.manage'), 403);

        $contractType->load('leaveRules');

        return view('admin.hr.contract-types.edit', [
            'contractType' => $contractType,
            'leaveTypes' => $this->activeLeaveTypes(),
        ]);
    }

    public function update(Request $request, ContractType $contractType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.contract-types.manage'), 403);

        $this->validateLeaveRules($request);
        $contractType->update($this->validateType($request, $contractType->id));
        $this->syncLeaveRules($request, $contractType);

        return redirect()
            ->route('admin.hr.contract-types.show', $contractType)
            ->with('status', 'Contract type updated.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, LeaveType>
     */
    private function activeLeaveTypes()
    {
        return LeaveType::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function validateLeaveRules(Request $request): void
    {
        $request->validate([
            'leave_rules' => ['nullable', 'array'],
            'leave_rules.*.leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'leave_rules.*.days_per_month' => ['nullable', 'numeric', 'min:0'],
            'leave_rules.*.requires_accrual' => ['nullable', 'boolean'],
            'leave_rules.*.applicable_gender' => ['nullable', 'string', 'in:all,male,female'],
            'leave_rules.*.enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function validateType(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:contract_types,code,'.$id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_permanent' => ['nullable', 'boolean'],
            'has_end_date' => ['nullable', 'boolean'],
            'default_duration_months' => ['nullable', 'integer', 'min:1'],
            'leave_accrual_enabled' => ['nullable', 'boolean'],
            'leave_days_per_month' => ['nullable', 'numeric', 'min:0'],
            'probation_months' => ['nullable', 'integer', 'min:0'],
            'renewable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_permanent' => $request->boolean('is_permanent'),
            'has_end_date' => $request->boolean('has_end_date', true),
            'leave_accrual_enabled' => $request->boolean('leave_accrual_enabled', true),
            'renewable' => $request->boolean('renewable'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function syncLeaveRules(Request $request, ContractType $type): void
    {
        $rules = $request->input('leave_rules', []);
        if (! is_array($rules)) {
            return;
        }

        ContractTypeLeaveRule::query()->where('contract_type_id', $type->id)->delete();

        foreach ($rules as $rule) {
            if (empty($rule['leave_type_id'])) {
                continue;
            }

            if (! filter_var($rule['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            ContractTypeLeaveRule::create([
                'contract_type_id' => $type->id,
                'leave_type_id' => (int) $rule['leave_type_id'],
                'days_per_month' => (float) ($rule['days_per_month'] ?? 0),
                'requires_accrual' => filter_var($rule['requires_accrual'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'applicable_gender' => $rule['applicable_gender'] ?? ContractTypeLeaveRule::GENDER_ALL,
            ]);
        }
    }
}
