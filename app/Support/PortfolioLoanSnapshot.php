<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PortfolioLoanSnapshot
{
    /**
     * @return array{
     *     active_loans_count: int,
     *     total_outstanding_balance: float,
     *     overdue_loans_count: int,
     *     total_overdue_amount: float,
     *     has_overdue: bool
     * }
     */
    public static function forCompany(Company $company): array
    {
        $activeLoansQuery = Loan::query()
            ->activePortfolio()
            ->whereHas('customer', fn (Builder $query) => $query->where('company_id', $company->id));

        return self::build($activeLoansQuery);
    }

    /**
     * @return array{
     *     active_loans_count: int,
     *     total_outstanding_balance: float,
     *     overdue_loans_count: int,
     *     total_overdue_amount: float,
     *     has_overdue: bool
     * }
     */
    public static function forCustomerGroup(CustomerGroup $customerGroup): array
    {
        $activeLoansQuery = Loan::query()
            ->activePortfolio()
            ->forCustomerGroupMembership($customerGroup->id);

        return self::build($activeLoansQuery);
    }

    /**
     * @return array{
     *     active_loans_count: int,
     *     total_outstanding_balance: float,
     *     overdue_loans_count: int,
     *     total_overdue_amount: float,
     *     has_overdue: bool
     * }
     */
    private static function build(Builder $activeLoansQuery): array
    {
        $today = Carbon::today();

        $overdueSchedulesQuery = LoanPaymentSchedule::query()
            ->where('remaining_amount', '>', 0)
            ->where(function ($query) use ($today) {
                $query->where('status', 'overdue')
                    ->orWhere('due_date', '<', $today);
            })
            ->whereHas('loan', function ($query) use ($activeLoansQuery) {
                $query->whereIn('id', (clone $activeLoansQuery)->select('loans.id'));
            });

        $totalOverdueAmount = (float) (clone $overdueSchedulesQuery)->sum('remaining_amount');

        return [
            'active_loans_count' => (int) (clone $activeLoansQuery)->count(),
            'total_outstanding_balance' => (float) (clone $activeLoansQuery)->sum('outstanding_balance'),
            'overdue_loans_count' => (clone $overdueSchedulesQuery)->pluck('loan_id')->unique()->count(),
            'total_overdue_amount' => $totalOverdueAmount,
            'has_overdue' => $totalOverdueAmount > 0,
        ];
    }
}
