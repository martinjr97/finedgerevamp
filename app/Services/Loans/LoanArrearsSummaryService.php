<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanArrearsAccrual;
use App\Models\LoanRepayment;
use App\Support\ArrearRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanArrearsSummaryService
{
    public function __construct(
        private readonly LoanArrearsAccrualService $accrualService,
    ) {}

    /**
     * @return array{
     *     overdue_installment_amount: float,
     *     arrears_interest_accrued: float,
     *     arrears_interest_paid: float,
     *     outstanding_arrears_interest: float,
     *     total_arrears_exposure: float,
     *     oldest_overdue_date: ?string,
     *     max_days_overdue: int,
     *     overdue_installments_count: int,
     *     npl_cutoff_date: string,
     *     performance_status: string,
     *     npl_at: ?string,
     *     arrear_rate: ?string,
     *     arrear_rate_display: ?string,
     *     is_npl: bool,
     * }
     */
    public function summarize(Loan $loan): array
    {
        $loan->loadMissing(['paymentSchedules']);

        $today = Carbon::today(config('arrears.timezone'));
        $overdueAmount = (float) $loan->getOverdueAmount();
        $accrued = $this->totalAccruedArrearsInterest($loan);
        $paid = $this->totalPaidArrearsInterest($loan);
        $outstandingArrears = max(0, round($accrued - $paid, 2));

        $overdueSchedules = $loan->getOverduePeriods();
        $oldest = $overdueSchedules->sortBy('due_date')->first();
        $maxDays = (int) $overdueSchedules->max('days_overdue');

        $nplCutoff = $this->accrualService->resolveNplCutoffDate($loan);

        return [
            'overdue_installment_amount' => round($overdueAmount, 2),
            'arrears_interest_accrued' => round($accrued, 2),
            'arrears_interest_paid' => round($paid, 2),
            'outstanding_arrears_interest' => $outstandingArrears,
            'total_arrears_exposure' => round($overdueAmount + $outstandingArrears, 2),
            'oldest_overdue_date' => $oldest?->due_date?->toDateString(),
            'max_days_overdue' => $maxDays,
            'overdue_installments_count' => $overdueSchedules->count(),
            'npl_cutoff_date' => $nplCutoff->toDateString(),
            'performance_status' => (string) ($loan->performance_status ?? 'performing'),
            'npl_at' => $loan->npl_at?->toDateString(),
            'arrear_rate' => $loan->arrear_rate !== null ? (string) $loan->arrear_rate : null,
            'arrear_rate_display' => ArrearRate::displayPercent($loan->arrear_rate !== null ? (string) $loan->arrear_rate : null),
            'is_npl' => ($loan->performance_status ?? 'performing') === 'npl' || $today->gt($nplCutoff),
        ];
    }

    public function totalAccruedArrearsInterest(Loan $loan): float
    {
        return (float) LoanArrearsAccrual::query()
            ->where('loan_id', $loan->id)
            ->sum('arrears_charge');
    }

    public function totalPaidArrearsInterest(Loan $loan): float
    {
        return (float) LoanRepayment::query()
            ->where('loan_id', $loan->id)
            ->sum('arrears_interest_amount');
    }

    public function outstandingArrearsInterest(Loan $loan): float
    {
        return max(0, round($this->totalAccruedArrearsInterest($loan) - $this->totalPaidArrearsInterest($loan), 2));
    }

    /**
     * Statement-friendly aggregated segments per installment.
     *
     * @return list<array<string, mixed>>
     */
    public function statementSegments(Loan $loan): array
    {
        $accruals = LoanArrearsAccrual::query()
            ->where('loan_id', $loan->id)
            ->with('paymentSchedule')
            ->orderBy('loan_payment_schedule_id')
            ->orderBy('accrual_date')
            ->get();

        if ($accruals->isEmpty()) {
            return [];
        }

        $segments = [];
        $grouped = $accruals->groupBy('loan_payment_schedule_id');

        foreach ($grouped as $scheduleId => $rows) {
            /** @var Collection<int, LoanArrearsAccrual> $rows */
            $schedule = $rows->first()->paymentSchedule;
            $current = null;

            foreach ($rows as $row) {
                $base = (string) $row->opening_overdue_amount;
                $rate = (string) $row->arrear_rate;

                if ($current === null
                    || $current['opening_overdue_amount'] !== $base
                    || $current['arrear_rate'] !== $rate
                    || ! Carbon::parse($current['period_end'])->addDay()->equalTo($row->accrual_date)
                ) {
                    if ($current !== null) {
                        $segments[] = $current;
                    }
                    $current = [
                        'loan_payment_schedule_id' => $scheduleId,
                        'due_date' => $schedule?->due_date?->toDateString(),
                        'period_start' => $row->accrual_date->toDateString(),
                        'period_end' => $row->accrual_date->toDateString(),
                        'opening_overdue_amount' => (float) $base,
                        'arrear_rate' => $rate,
                        'arrear_rate_display' => ArrearRate::displayPercent($rate),
                        'days_accrued' => 1,
                        'arrears_interest' => (float) $row->arrears_charge,
                    ];
                } else {
                    $current['period_end'] = $row->accrual_date->toDateString();
                    $current['days_accrued']++;
                    $current['arrears_interest'] = round($current['arrears_interest'] + (float) $row->arrears_charge, 2);
                }
            }

            if ($current !== null) {
                $segments[] = $current;
            }
        }

        return $segments;
    }
}
