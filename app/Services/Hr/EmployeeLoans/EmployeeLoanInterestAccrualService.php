<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanAccrual;
use Carbon\Carbon;

class EmployeeLoanInterestAccrualService
{
    public function accrueForDate(Carbon $date): int
    {
        $count = 0;

        $loans = EmployeeLoan::query()
            ->activePortfolio()
            ->where('accrual_type', 'daily')
            ->get();

        foreach ($loans as $loan) {
            if ($this->accrueLoanForDate($loan, $date)) {
                $count++;
            }
        }

        return $count;
    }

    public function accrueLoanForDate(EmployeeLoan $loan, Carbon $date): bool
    {
        if (EmployeeLoanAccrual::query()
            ->where('employee_loan_id', $loan->id)
            ->whereDate('accrual_date', $date->toDateString())
            ->exists()) {
            return false;
        }

        $dailyRate = (float) ($loan->daily_rate ?? 0);
        if ($dailyRate <= 0) {
            return false;
        }

        $principalBalance = max(0, (float) $loan->principal_amount - (float) $loan->repayments()->sum('principal_amount'));
        $interestAmount = round($principalBalance * $dailyRate, 2);

        if ($interestAmount <= 0) {
            return false;
        }

        $cumulative = round((float) $loan->interest_accrued + $interestAmount, 2);

        EmployeeLoanAccrual::create([
            'employee_loan_id' => $loan->id,
            'accrual_date' => $date->toDateString(),
            'principal_balance' => $principalBalance,
            'interest_amount' => $interestAmount,
            'cumulative_interest' => $cumulative,
            'total_balance' => round($principalBalance + $cumulative, 2),
            'accrual_period' => $loan->accrual_period,
            'rate_used' => $dailyRate,
        ]);

        $loan->update([
            'interest_accrued' => $cumulative,
            'last_accrual_date' => $date->toDateString(),
        ]);

        return true;
    }
}
