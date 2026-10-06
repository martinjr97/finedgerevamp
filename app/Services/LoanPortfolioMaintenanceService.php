<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;

class LoanPortfolioMaintenanceService
{
    /**
     * Accrue daily interest from loan start (or last accrual) through yesterday.
     */
    public function catchUpDailyAccrual(Loan $loan, ?Carbon $throughDate = null): int
    {
        if ($loan->accrual_type !== 'daily' || $loan->status !== 'active') {
            return 0;
        }

        $through = ($throughDate ?? Carbon::yesterday())->startOfDay();
        $start = Carbon::parse($loan->loan_start_date)->startOfDay();

        if ($loan->last_accrual_date) {
            $start = Carbon::parse($loan->last_accrual_date)->addDay()->startOfDay();
        }

        if ($start->gt($through)) {
            return 0;
        }

        $processed = 0;
        for ($date = $start->copy(); $date->lte($through); $date->addDay()) {
            if ($loan->loan_end_date && $date->gt(Carbon::parse($loan->loan_end_date))) {
                break;
            }

            $exists = $loan->accruals()->whereDate('accrual_date', $date)->exists();
            if ($exists) {
                continue;
            }

            $loan->accrueInterestForDate($date);
            $processed++;
        }

        return $processed;
    }

    /**
     * Refresh persisted schedule aging fields for one loan.
     */
    public function refreshScheduleAging(Loan $loan): int
    {
        $updated = 0;

        foreach ($loan->paymentSchedules()->where('remaining_amount', '>', 0)->get() as $schedule) {
            $schedule->updateStatus();
            $updated++;
        }

        return $updated;
    }

    /**
     * Refresh aging for all active disbursed loans (migrated shadow portfolio).
     */
    public function refreshAllActiveScheduleAging(): int
    {
        $total = 0;

        Loan::query()
            ->activePortfolio()
            ->orderBy('id')
            ->chunkById(100, function ($loans) use (&$total) {
                foreach ($loans as $loan) {
                    $total += $this->refreshScheduleAging($loan);
                }
            });

        return $total;
    }
}
