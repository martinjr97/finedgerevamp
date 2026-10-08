<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\Hr\LeaveApplicationService;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\LeaveDurationCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveApplicationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.view'), 403);

        $applications = LeaveApplication::query()
            ->with(['employee.hrDepartment', 'leaveType', 'approver'])
            ->where('status', LeaveApplication::STATUS_PENDING)
            ->orderBy('start_date')
            ->paginate(25);

        $canApprove = auth('admin')->user()?->can('hr.leave.approve') ?? false;

        return view('admin.hr.leave.applications-index', compact('applications', 'canApprove'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.apply'), 403);

        $admin = auth('admin')->user();
        $canApproveImmediately = ($admin?->can('hr.leave.apply') && $admin?->can('hr.leave.approve')) ?? false;

        $leaveTypes = LeaveType::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.hr.leave.applications-create', [
            'employees' => Employee::query()->active()->orderBy('first_name')->get(),
            'leaveTypes' => $leaveTypes,
            'leaveTypesForForm' => $leaveTypes->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'accrual_based' => (bool) $type->accrual_based,
            ])->values()->all(),
            'canApproveImmediately' => $canApproveImmediately,
            'balancePreviewUrl' => route('admin.hr.leave.applications.balance-preview'),
        ]);
    }

    public function balancePreview(Request $request, LeaveBalanceService $balanceService): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.apply'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
        ]);

        $leaveType = LeaveType::query()->findOrFail($validated['leave_type_id']);
        $balance = $balanceService->balanceForType($validated['employee_id'], $leaveType->id);

        return response()->json([
            'available' => $balance['available'],
            'accrual_based' => (bool) $leaveType->accrual_based,
        ]);
    }

    public function store(
        Request $request,
        LeaveDurationCalculator $calculator,
        LeaveBalanceService $balanceService,
        LeaveApplicationService $applicationService,
    ): RedirectResponse {
        abort_unless(auth('admin')->user()?->can('hr.leave.apply'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            'approve_now' => ['nullable', 'boolean'],
            'approver_comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $leaveType = LeaveType::query()->findOrFail($validated['leave_type_id']);
        $days = $calculator->calculateDays($validated['start_date'], $validated['end_date']);

        if ($leaveType->accrual_based && ! $balanceService->hasSufficientBalance($validated['employee_id'], $leaveType->id, $days)) {
            $available = $balanceService->balanceForType($validated['employee_id'], $leaveType->id)['available'];

            return back()->withInput()->withErrors([
                'end_date' => sprintf(
                    'Insufficient leave balance for %s. Available: %s day(s), requested: %s day(s).',
                    $leaveType->name,
                    number_format($available, 2),
                    number_format($days, 2),
                ),
            ]);
        }

        $wantsImmediateApproval = $request->boolean('approve_now');
        $canApprove = auth('admin')->user()?->can('hr.leave.approve') ?? false;
        if ($wantsImmediateApproval && ! $canApprove) {
            return back()->withInput()->withErrors([
                'approve_now' => 'You do not have permission to approve leave.',
            ]);
        }

        $application = LeaveApplication::create([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'days_requested' => $days,
            'status' => LeaveApplication::STATUS_PENDING,
            'created_by' => auth('admin')->id(),
        ]);

        if ($wantsImmediateApproval && $canApprove) {
            try {
                $applicationService->approve(
                    $application,
                    auth('admin')->id(),
                    $validated['approver_comments'] ?? null,
                );
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('admin.hr.leave.applications.index')
                    ->with('status', 'Leave submitted for approval.')
                    ->with('error', 'Could not approve immediately: '.$e->getMessage());
            }

            return redirect()->route('admin.hr.leave.history.index')
                ->with('status', 'Leave application submitted and approved.');
        }

        return redirect()->route('admin.hr.leave.applications.index')
            ->with('status', 'Leave application submitted and is pending approval.');
    }

    public function approve(LeaveApplication $leaveApplication, Request $request, LeaveApplicationService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.approve'), 403);

        try {
            $service->approve($leaveApplication, auth('admin')->id(), $request->input('comments'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Leave approved.');
    }

    public function reject(LeaveApplication $leaveApplication, Request $request, LeaveApplicationService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.approve'), 403);

        $service->reject($leaveApplication, auth('admin')->id(), $request->input('comments'));

        return back()->with('status', 'Leave rejected.');
    }

    public function cancel(LeaveApplication $leaveApplication, LeaveApplicationService $service): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.manage'), 403);

        try {
            $service->cancelApproved($leaveApplication, auth('admin')->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Approved leave cancelled and reversed.');
    }
}
