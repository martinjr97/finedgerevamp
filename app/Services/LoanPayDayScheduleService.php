<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Build repayment due dates from cut-off day and pay day (MOU / government products).
 */
class LoanPayDayScheduleService
{
    /**
     * @return array{
     *     first_payment_date: Carbon,
     *     last_payment_date: Carbon,
     *     loan_end_date: Carbon,
     *     days: int,
     *     payment_due_dates: list<string>
     * }
     */
    public function calculateDueDates(Carbon $loanStartDate, int $tenureMonths, int $cutOffDay, int $payDay): array
    {
        if ($tenureMonths < 1) {
            throw new InvalidArgumentException('tenure_months must be at least 1.');
        }

        if ($cutOffDay < 1 || $cutOffDay > 31 || $payDay < 1 || $payDay > 31) {
            throw new InvalidArgumentException('Cut-off day and pay day must be between 1 and 31.');
        }

        $cycleDate = $loanStartDate->copy();
        if ($loanStartDate->day > $cutOffDay) {
            $cycleDate = $cycleDate->copy()->addMonthNoOverflow()->startOfMonth();
        }

        $dueDates = [];
        $currentYear = $cycleDate->year;
        $currentMonth = $cycleDate->month;

        for ($i = 0; $i < $tenureMonths; $i++) {
            if ($i > 0) {
                $nextCycle = Carbon::create($currentYear, $currentMonth, 1)->addMonthNoOverflow();
                $currentYear = $nextCycle->year;
                $currentMonth = $nextCycle->month;
            }

            $dueDates[] = $this->dueDateForCycle($currentYear, $currentMonth, $cutOffDay, $payDay);
        }

        $firstPaymentDate = $dueDates[0];
        $lastPaymentDate = $dueDates[array_key_last($dueDates)];

        return [
            'first_payment_date' => $firstPaymentDate,
            'last_payment_date' => $lastPaymentDate,
            'loan_end_date' => $lastPaymentDate->copy(),
            'days' => $loanStartDate->diffInDays($lastPaymentDate),
            'payment_due_dates' => array_map(
                static fn (Carbon $date) => $date->toDateString(),
                $dueDates
            ),
        ];
    }

    private function dueDateForCycle(int $cycleYear, int $cycleMonth, int $cutOffDay, int $payDay): Carbon
    {
        $dueYear = $cycleYear;
        $dueMonth = $cycleMonth;

        if ($payDay <= $cutOffDay) {
            $next = Carbon::create($cycleYear, $cycleMonth, 1)->addMonthNoOverflow();
            $dueYear = $next->year;
            $dueMonth = $next->month;
        }

        $endOfMonth = Carbon::create($dueYear, $dueMonth, 1)->endOfMonth();
        $day = min($payDay, $endOfMonth->day);

        return Carbon::create($dueYear, $dueMonth, $day);
    }
}
