<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Loan;
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

    public static function outstandingBalanceForCompany(Company $company): float
    {
        return self::outstandingBalancesForCompanies([$company->id])[$company->id] ?? 0.0;
    }

    public static function outstandingBalanceForCustomerGroup(CustomerGroup $customerGroup): float
    {
        return self::outstandingBalancesForCustomerGroups([$customerGroup->id])[$customerGroup->id] ?? 0.0;
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<int, float>
     */
    public static function outstandingBalancesForCompanies(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return Loan::query()
            ->join('customers', 'customers.id', '=', 'loans.customer_id')
            ->where('loans.status', 'active')
            ->where('loans.disbursement_status', 'completed')
            ->whereIn('customers.company_id', $companyIds)
            ->groupBy('customers.company_id')
            ->selectRaw('customers.company_id as portfolio_key, SUM(GREATEST(loans.outstanding_balance, 0)) as total')
            ->pluck('total', 'portfolio_key')
            ->mapWithKeys(fn ($total, $companyId) => [(int) $companyId => round((float) $total, 2)])
            ->all();
    }

    /**
     * @param  list<int>  $customerGroupIds
     * @return array<int, float>
     */
    public static function outstandingBalancesForCustomerGroups(array $customerGroupIds): array
    {
        if ($customerGroupIds === []) {
            return [];
        }

        return Loan::query()
            ->join('customers', 'customers.id', '=', 'loans.customer_id')
            ->where('loans.status', 'active')
            ->where('loans.disbursement_status', 'completed')
            ->whereIn('customers.customer_group_id', $customerGroupIds)
            ->groupBy('customers.customer_group_id')
            ->selectRaw('customers.customer_group_id as portfolio_key, SUM(GREATEST(loans.outstanding_balance, 0)) as total')
            ->pluck('total', 'portfolio_key')
            ->mapWithKeys(fn ($total, $groupId) => [(int) $groupId => round((float) $total, 2)])
            ->all();
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
        $loans = (clone $activeLoansQuery)
            ->with('paymentSchedules')
            ->get(['id', 'outstanding_balance']);

        $totalOutstanding = 0.0;
        $totalOverdue = 0.0;
        $overdueLoansCount = 0;

        foreach ($loans as $loan) {
            $outstanding = max(0.0, (float) $loan->outstanding_balance);
            $totalOutstanding += $outstanding;

            $overdue = min((float) $loan->getOverdueAmount(), $outstanding);
            if ($overdue <= 0) {
                continue;
            }

            $overdueLoansCount++;
            $totalOverdue += $overdue;
        }

        return [
            'active_loans_count' => $loans->count(),
            'total_outstanding_balance' => round($totalOutstanding, 2),
            'overdue_loans_count' => $overdueLoansCount,
            'total_overdue_amount' => round($totalOverdue, 2),
            'has_overdue' => $totalOverdue > 0,
        ];
    }
}
