<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanPaymentSchedule;
use App\Models\Repayment;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with statistics.
     */
    public function index(): View
    {
        $admin = auth('admin')->user();
        $admin->loadMissing('branch');
        $companyFilterId = $admin->getCompanyFilterId();
        
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek()->startOfDay();
        $endOfWeek = Carbon::now()->endOfWeek()->endOfDay();

        // Build base queries with company filtering if needed
        $loanQuery = Loan::query();
        $customerQuery = Customer::query();
        $repaymentQuery = Repayment::query();
        
        if ($companyFilterId !== null) {
            // Filter loans by customer's company
            $loanQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            
            // Filter customers by company
            $customerQuery->where('company_id', $companyFilterId);
            
            // Filter repayments by customer's company
            $repaymentQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        // Today's stats
        $todayStats = [
            'loans_created' => (clone $loanQuery)->whereDate('created_at', $today)->count(),
            'loans_approved' => (clone $loanQuery)->whereDate('approved_at', $today)->whereNotNull('approved_at')->count(),
            'loans_disbursed' => (clone $loanQuery)->whereDate('disbursed_at', $today)->whereNotNull('disbursed_at')->count(),
            'total_disbursed' => (clone $loanQuery)->whereDate('disbursed_at', $today)
                ->whereNotNull('disbursed_at')
                ->sum('principal_amount'),
            'repayments_received' => (clone $repaymentQuery)->whereDate('processed_at', $today)
                ->where('status', 'completed')
                ->whereNotNull('processed_at')
                ->count(),
            'total_repayments' => (clone $repaymentQuery)->whereDate('processed_at', $today)
                ->where('status', 'completed')
                ->whereNotNull('processed_at')
                ->sum('total_amount'),
            'new_customers' => (clone $customerQuery)->whereDate('created_at', $today)->count(),
        ];

        // This week's stats - use >= and <= to ensure we capture all records
        $weekStats = [
            'loans_created' => (clone $loanQuery)->where('created_at', '>=', $startOfWeek)
                ->where('created_at', '<=', $endOfWeek)
                ->count(),
            'loans_approved' => (clone $loanQuery)->where('approved_at', '>=', $startOfWeek)
                ->where('approved_at', '<=', $endOfWeek)
                ->whereNotNull('approved_at')
                ->count(),
            'loans_disbursed' => (clone $loanQuery)->where('disbursed_at', '>=', $startOfWeek)
                ->where('disbursed_at', '<=', $endOfWeek)
                ->whereNotNull('disbursed_at')
                ->count(),
            'total_disbursed' => (clone $loanQuery)->where('disbursed_at', '>=', $startOfWeek)
                ->where('disbursed_at', '<=', $endOfWeek)
                ->whereNotNull('disbursed_at')
                ->sum('principal_amount'),
            'repayments_received' => (clone $repaymentQuery)->where('processed_at', '>=', $startOfWeek)
                ->where('processed_at', '<=', $endOfWeek)
                ->where('status', 'completed')
                ->whereNotNull('processed_at')
                ->count(),
            'total_repayments' => (clone $repaymentQuery)->where('processed_at', '>=', $startOfWeek)
                ->where('processed_at', '<=', $endOfWeek)
                ->where('status', 'completed')
                ->whereNotNull('processed_at')
                ->sum('total_amount'),
            'new_customers' => (clone $customerQuery)->where('created_at', '>=', $startOfWeek)
                ->where('created_at', '<=', $endOfWeek)
                ->count(),
        ];

        // Overall stats
        $overallStatsQuery = Company::query();
        $loanProductQuery = LoanProduct::query();
        $pendingAdminsQuery = Admin::where('approval_status', 'pending');
        $pendingCompaniesQuery = Company::where('approval_status', 'pending');
        $pendingCustomersQuery = Customer::where('approval_status', 'pending');
        $pendingLoansQuery = (clone $loanQuery)->where('status', 'pending_approval');
        $pendingTransfersQuery = FinancialTransaction::where('type', 'transfer')->where('approval_status', 'pending');
        $pendingRepaymentsQuery = (clone $repaymentQuery)->where('status', 'pending');
        
        if ($companyFilterId !== null) {
            $overallStatsQuery->where('id', $companyFilterId);
            $loanProductQuery->where('company_id', $companyFilterId);
            $pendingAdminsQuery->where('company_id', $companyFilterId);
            $pendingCompaniesQuery->where('id', $companyFilterId);
            $pendingCustomersQuery->where(function ($query) use ($companyFilterId) {
                $query->where('company_id', $companyFilterId)
                    ->orWhere(function ($subQuery) use ($companyFilterId) {
                        $subQuery->whereNull('company_id')
                            ->whereHas('loanProduct', function ($loanProductQuery) use ($companyFilterId) {
                                $loanProductQuery->where('company_id', $companyFilterId);
                            });
                    });
            });
            $pendingLoansQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $pendingTransfersQuery->whereHas('creator', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $pendingRepaymentsQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }
        
        // Calculate total repayments due today (only remaining amounts, excluding fully paid schedules)
        $paymentScheduleQuery = LoanPaymentSchedule::query()
            ->whereDate('due_date', $today)
            ->where('remaining_amount', '>', 0); // Only include schedules with remaining balance
        
        if ($companyFilterId !== null) {
            $paymentScheduleQuery->whereHas('loan.customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }
        
        $totalRepaymentsDueToday = $paymentScheduleQuery->sum('remaining_amount');
        
        $overallStats = [
            'active_companies' => $overallStatsQuery->count(),
            'loan_products' => $loanProductQuery->count(),
            'total_customers' => (clone $customerQuery)->count(),
            'active_loans' => (clone $loanQuery)->activePortfolio()->count(),
            'pending_approvals' => $pendingAdminsQuery->count()
                + $pendingCompaniesQuery->count()
                + $pendingCustomersQuery->count()
                + $pendingLoansQuery->count()
                + $pendingTransfersQuery->count()
                + $pendingRepaymentsQuery->count(),
            'total_outstanding' => (clone $loanQuery)->activePortfolio()->sum('outstanding_balance'),
            'total_repayments_due_today' => $totalRepaymentsDueToday,
        ];

        $hour = now()->hour;
        $greeting = $hour < 12
            ? 'Good morning'
            : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $adminGreeting = [
            'greeting' => $greeting,
            'name' => $admin->full_name ?: $admin->first_name ?: 'Admin',
            'branch' => $admin->branch?->name,
            'note' => $admin->branch?->name
                ? 'Welcome back to '.$admin->branch->name.' branch.'
                : 'Welcome back. Here is your latest dashboard activity.',
        ];

        $loanTakeoutTrendBaseQuery = Loan::query()
            ->whereNotNull('disbursed_at');
        $repaymentTrendBaseQuery = Repayment::query()
            ->where('status', 'completed')
            ->whereNotNull('processed_at');

        if ($companyFilterId !== null) {
            $loanTakeoutTrendBaseQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $repaymentTrendBaseQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        $trendMonths = collect(range(6, 0))
            ->map(fn (int $offset) => Carbon::now()->startOfMonth()->subMonths($offset));

        $monthlyTrend = [
            'labels' => [],
            'loan_takeouts' => [],
            'repayments' => [],
        ];

        foreach ($trendMonths as $month) {
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthlyTrend['labels'][] = $month->format('M Y');
            $monthlyTrend['loan_takeouts'][] = (float) (clone $loanTakeoutTrendBaseQuery)
                ->whereBetween('disbursed_at', [$monthStart, $monthEnd])
                ->sum('principal_amount');
            $monthlyTrend['repayments'][] = (float) (clone $repaymentTrendBaseQuery)
                ->whereBetween('processed_at', [$monthStart, $monthEnd])
                ->sum('total_amount');
        }

        $pendingDisbursementQuery = Loan::query()
            ->where('status', 'approved')
            ->where('disbursement_status', 'pending');

        if ($companyFilterId !== null) {
            $pendingDisbursementQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        $pendingDisbursementCount = (clone $pendingDisbursementQuery)->count();
        $overduePendingDisbursementCount = (clone $pendingDisbursementQuery)
            ->whereDate('loan_start_date', '<', $today)
            ->count();
        $pendingDisbursementAmount = (clone $pendingDisbursementQuery)->sum('principal_amount');

        $pendingDisbursementLoans = (clone $pendingDisbursementQuery)
            ->with([
                'customer:id,first_name,last_name,email',
                'loanProduct:id,name',
            ])
            ->orderByRaw("CASE WHEN loan_start_date < ? THEN 0 ELSE 1 END", [$today->toDateString()])
            ->orderBy('loan_start_date')
            ->orderBy('created_at')
            ->limit(12)
            ->get();

        return view('admin.dashboard', [
            'todayStats' => $todayStats,
            'weekStats' => $weekStats,
            'overallStats' => $overallStats,
            'adminGreeting' => $adminGreeting,
            'monthlyTrend' => $monthlyTrend,
            'pendingDisbursementCount' => $pendingDisbursementCount,
            'overduePendingDisbursementCount' => $overduePendingDisbursementCount,
            'pendingDisbursementAmount' => $pendingDisbursementAmount,
            'pendingDisbursementLoans' => $pendingDisbursementLoans,
            'dashboardToday' => $today,
        ]);
    }
}
