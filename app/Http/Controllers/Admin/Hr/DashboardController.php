<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\LeaveApplication;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.dashboard.view'), 403);

        $totalEmployees = Employee::query()->count();
        $activeEmployees = Employee::query()->active()->count();
        $departmentCount = Department::query()->where('is_active', true)->count();
        $pendingLeave = LeaveApplication::query()->where('status', LeaveApplication::STATUS_PENDING)->count();

        $onLeaveToday = LeaveApplication::query()
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->count();

        $expiring30 = EmployeeContract::query()->expiringWithin(30)->count();
        $expiring60 = EmployeeContract::query()->expiringWithin(60)->count();
        $expiring90 = EmployeeContract::query()->expiringWithin(90)->count();

        $expiringContracts = EmployeeContract::query()
            ->expiringWithin(90)
            ->with(['employee', 'contractType'])
            ->orderBy('end_date')
            ->limit(10)
            ->get();

        $recentEmployees = Employee::query()->latest()->limit(8)->get();
        $pendingApplications = LeaveApplication::query()
            ->where('status', LeaveApplication::STATUS_PENDING)
            ->with(['employee', 'leaveType'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.hr.dashboard', compact(
            'totalEmployees',
            'activeEmployees',
            'departmentCount',
            'pendingLeave',
            'onLeaveToday',
            'expiring30',
            'expiring60',
            'expiring90',
            'expiringContracts',
            'recentEmployees',
            'pendingApplications',
        ));
    }
}
