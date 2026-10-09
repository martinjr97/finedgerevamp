<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanArrearsAccrual;
use App\Models\EmployeeLoanPaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeLoanArrearsAccrualService
{
    public function accrueForDate(Carbon $date): int
    {
        $count = 0;
        $loans = EmployeeLoan::query()
            ->activePortfolio()
            ->where('arrear_rate', '>', 0)
            ->get();

        foreach ($loans as $loan) {
            if ($this->isPastCutoff($loan, $date)) {
                $this->markNplIfNeeded($loan, $date);

                continue;
            }

            $count += $this->accrueLoanForDate($loan, $date);
        }

        return $count;
    }

    protected function isPastCutoff(EmployeeLoan $loan, Carbon $date): bool
    {
        $finalDue = $loan->paymentSchedules()->max('due_date');
        if (! $finalDue) {
            return false;
        }

        $cutoff = Carbon::parse($finalDue)->addDays(90);

        return $date->gt($cutoff);
    }

    protected function markNplIfNeeded(EmployeeLoan $loan, Carbon $date): void
    {
        if ($loan->performance_status === 'npl') {
            return;
        }

        if ((float) $loan->outstanding_balance <= 0) {
            return;
        }

        $loan->update([
            'performance_status' => 'npl',
            'npl_at' => $loan->npl_at ?? $date,
        ]);
    }

    protected function accrueLoanForDate(EmployeeLoan $loan, Carbon $date): int
    {
        $dailyRate = (float) $loan->arrear_rate;
        if ($dailyRate <= 0) {
            return 0;
        }

        $created = 0;
        $schedules = $loan->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', $date->toDateString())
            ->get();

        foreach ($schedules as $schedule) {
            $exists = EmployeeLoanArrearsAccrual::query()
                ->where('employee_loan_id', $loan->id)
                ->where('employee_loan_payment_schedule_id', $schedule->id)
                ->whereDate('accrual_date', $date->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            $base = (float) $schedule->remaining_amount;
            $interest = round($base * $dailyRate, 2);

            if ($interest <= 0) {
                continue;
            }

            EmployeeLoanArrearsAccrual::create([
                'employee_loan_id' => $loan->id,
                'employee_loan_payment_schedule_id' => $schedule->id,
                'accrual_date' => $date->toDateString(),
                'base_amount' => $base,
                'interest_amount' => $interest,
                'rate_used' => $dailyRate,
            ]);

            $created++;
        }

        if ($created > 0) {
            $loan->update(['arrears_last_accrual_date' => $date->toDateString()]);
        }

        return $created;
    }
}
