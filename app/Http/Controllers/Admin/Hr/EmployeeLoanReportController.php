<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPaymentSchedule;
use App\Models\EmployeeLoanRepayment;
use App\Models\LoanRate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeLoanReportController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $active = EmployeeLoan::query()->activePortfolio();
        $kpis = [
            'total_loans' => EmployeeLoan::query()->count(),
            'active_loans' => (clone $active)->count(),
            'employees_with_loans' => EmployeeLoan::query()->distinct('employee_id')->count('employee_id'),
            'total_disbursed' => (float) EmployeeLoan::query()->where('disbursement_status', 'completed')->sum('principal_amount'),
            'outstanding_balance' => (float) (clone $active)->sum('outstanding_balance'),
            'monthly_repayments' => (float) EmployeeLoanRepayment::query()
                ->where('status', 'completed')
                ->whereMonth('processed_at', now()->month)
                ->whereYear('processed_at', now()->year)
                ->sum('amount'),
            'interest_income' => (float) EmployeeLoanRepayment::query()->where('status', 'completed')->sum('interest_amount'),
            'arrears_outstanding' => (float) EmployeeLoanPaymentSchedule::query()->where('status', 'overdue')->sum('remaining_amount'),
            'npl_count' => EmployeeLoan::query()->where('performance_status', 'npl')->count(),
        ];

        return view('admin.hr.employee-loans.reports.index', compact('kpis'));
    }

    public function portfolio(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $loans = EmployeeLoan::query()
            ->with(['employee.hrDepartment', 'loanRate'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.hr.employee-loans.reports.portfolio', compact('loans'));
    }

    public function repayments(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $repayments = EmployeeLoanRepayment::query()
            ->with(['employeeLoan.employee'])
            ->where('status', 'completed')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('effective_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('effective_date', '<=', $request->date('to')))
            ->latest('effective_date')
            ->paginate(50)
            ->withQueryString();

        return view('admin.hr.employee-loans.reports.repayments', compact('repayments'));
    }

    public function arrears(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $schedules = EmployeeLoanPaymentSchedule::query()
            ->with(['employeeLoan.employee'])
            ->where('remaining_amount', '>', 0)
            ->where('status', 'overdue')
            ->orderByDesc('days_overdue')
            ->paginate(50);

        return view('admin.hr.employee-loans.reports.arrears', compact('schedules'));
    }

    public function aging(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $buckets = [
            'current' => EmployeeLoanPaymentSchedule::query()->where('remaining_amount', '>', 0)->where('days_overdue', 0)->count(),
            '1_30' => EmployeeLoanPaymentSchedule::query()->whereBetween('days_overdue', [1, 30])->count(),
            '31_60' => EmployeeLoanPaymentSchedule::query()->whereBetween('days_overdue', [31, 60])->count(),
            '61_90' => EmployeeLoanPaymentSchedule::query()->whereBetween('days_overdue', [61, 90])->count(),
            '90_plus' => EmployeeLoanPaymentSchedule::query()->where('days_overdue', '>', 90)->count(),
        ];

        return view('admin.hr.employee-loans.reports.aging', compact('buckets'));
    }

    public function byDepartment(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $rows = EmployeeLoan::query()
            ->selectRaw('employees.department_id, SUM(employee_loans.outstanding_balance) as outstanding, COUNT(*) as loan_count')
            ->join('employees', 'employees.id', '=', 'employee_loans.employee_id')
            ->groupBy('employees.department_id')
            ->get()
            ->map(function ($row) {
                $dept = Department::query()->find($row->department_id);

                return [
                    'department' => $dept?->name ?? 'Unassigned',
                    'loan_count' => (int) $row->loan_count,
                    'outstanding' => (float) $row->outstanding,
                ];
            });

        return view('admin.hr.employee-loans.reports.by-department', compact('rows'));
    }

    public function active(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $loans = EmployeeLoan::query()
            ->activePortfolio()
            ->with(['employee.hrDepartment', 'loanRate'])
            ->latest('id')
            ->paginate(50);

        return view('admin.hr.employee-loans.reports.active', compact('loans'));
    }

    public function settled(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $loans = EmployeeLoan::query()
            ->where('status', EmployeeLoan::STATUS_SETTLED)
            ->with(['employee.hrDepartment', 'loanRate'])
            ->latest('settled_at')
            ->paginate(50);

        return view('admin.hr.employee-loans.reports.settled', compact('loans'));
    }

    public function byEmployee(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $rows = EmployeeLoan::query()
            ->selectRaw('employee_id, COUNT(*) as loan_count, SUM(principal_amount) as total_borrowed, SUM(outstanding_balance) as outstanding')
            ->groupBy('employee_id')
            ->get()
            ->map(function ($row) {
                $employee = Employee::query()->with('hrDepartment')->find($row->employee_id);

                return [
                    'employee' => $employee,
                    'loan_count' => (int) $row->loan_count,
                    'total_borrowed' => (float) $row->total_borrowed,
                    'outstanding' => (float) $row->outstanding,
                ];
            });

        return view('admin.hr.employee-loans.reports.by-employee', compact('rows'));
    }

    public function byRate(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $rows = EmployeeLoan::query()
            ->selectRaw('loan_rate_id, COUNT(*) as loan_count, SUM(outstanding_balance) as outstanding')
            ->groupBy('loan_rate_id')
            ->get()
            ->map(function ($row) {
                $rate = LoanRate::query()->with('loanRateType')->find($row->loan_rate_id);

                return [
                    'rate' => $rate,
                    'loan_count' => (int) $row->loan_count,
                    'outstanding' => (float) $row->outstanding,
                ];
            });

        return view('admin.hr.employee-loans.reports.by-rate', compact('rows'));
    }

    public function byTerm(): View
    {
        abort_unless(auth('admin')->user()?->can('hr.employee-loans.reports'), 403);

        $rows = EmployeeLoan::query()
            ->selectRaw('tenure_months, COUNT(*) as loan_count, SUM(principal_amount) as total_principal, SUM(outstanding_balance) as outstanding')
            ->groupBy('tenure_months')
            ->orderBy('tenure_months')
            ->get();

        return view('admin.hr.employee-loans.reports.by-term', compact('rows'));
    }
}
