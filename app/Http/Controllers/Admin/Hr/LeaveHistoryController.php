<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.leave.view'), 403);

        $query = LeaveApplication::query()
            ->with(['employee.hrDepartment', 'leaveType', 'approver'])
            ->whereIn('status', [
                LeaveApplication::STATUS_APPROVED,
                LeaveApplication::STATUS_REJECTED,
            ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->integer('leave_type_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        $history = $query->orderByDesc('start_date')->paginate(30)->withQueryString();

        return view('admin.hr.leave.history-index', [
            'history' => $history,
            'departments' => Department::query()->orderBy('name')->get(),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
        ]);
    }
}
