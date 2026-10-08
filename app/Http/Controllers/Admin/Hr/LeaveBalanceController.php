<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLeaveTransaction;
use App\Models\LeaveType;
use App\Services\Hr\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function index(Request $request, LeaveBalanceService $balanceService): View
    {
        abort_unless(auth('admin')->user()?->can('hr.leave-balances.view'), 403);

        $leaveTypes = LeaveType::query()->where('is_active', true)->orderBy('name')->get();
        $employees = Employee::query()->active()->with('hrDepartment')->orderBy('first_name')->get();

        $filterEmployeeId = $request->filled('employee_id') ? $request->integer('employee_id') : null;
        $displayEmployees = $filterEmployeeId
            ? $employees->where('id', $filterEmployeeId)->values()
            : $employees;

        $balanceMatrix = $balanceService->balancesForEmployees($displayEmployees, $leaveTypes);
        $filteredEmployee = $filterEmployeeId ? $displayEmployees->first() : null;
        $employeeDetailBalances = $filteredEmployee
            ? $balanceService->balancesForEmployee($filteredEmployee)
            : [];

        return view('admin.hr.leave.balances-index', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'filterEmployeeId' => $filterEmployeeId,
            'displayEmployees' => $displayEmployees,
            'balanceMatrix' => $balanceMatrix,
            'filteredEmployee' => $filteredEmployee,
            'employeeDetailBalances' => $employeeDetailBalances,
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.leave-balances.adjust'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'days' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        EmployeeLeaveTransaction::create([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'transaction_date' => now()->toDateString(),
            'type' => EmployeeLeaveTransaction::TYPE_ADJUSTMENT,
            'days' => $validated['days'],
            'description' => $validated['description'] ?? 'Manual adjustment',
            'created_by' => auth('admin')->id(),
        ]);

        $redirectParams = [];
        if ($request->filled('return_employee_id')) {
            $redirectParams['employee_id'] = $request->integer('return_employee_id');
        }

        return redirect()
            ->route('admin.hr.leave.balances.index', $redirectParams)
            ->with('status', 'Leave balance adjusted.');
    }
}
