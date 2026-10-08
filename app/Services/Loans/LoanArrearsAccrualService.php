<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanArrearsAccrual;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanRepayment;
use App\Support\ArrearRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanArrearsAccrualService
{
    /**
     * @return array{
     *     loans_scanned: int,
     *     accruals_created: int,
     *     accruals_skipped_existing: int,
     *     accruals_adjusted: int,
     *     overdue_installments: int,
     *     loans_classified_npl: int,
     *     total_arrears_charged: string,
     *     errors: int
     * }
     */
    public function accruePortfolio(
        ?Carbon $throughDate = null,
        ?Carbon $fromDate = null,
        ?int $loanId = null,
        bool $allowHistoricalBackfill = false,
    ): array {
        $throughDate ??= Carbon::today(config('arrears.timezone'));
        $engineEffective = $this->resolveEngineEffectiveDate();

        $stats = [
            'loans_scanned' => 0,
            'accruals_created' => 0,
            'accruals_skipped_existing' => 0,
            'accruals_adjusted' => 0,
            'overdue_installments' => 0,
            'loans_classified_npl' => 0,
            'total_arrears_charged' => '0.00',
            'errors' => 0,
        ];

        $query = Loan::query()
            ->activePortfolio()
            ->whereNotNull('arrear_rate')
            ->where('arrear_rate', '>', 0)
            ->whereNotIn('status', ['settled', 'completed', 'cancelled']);

        if ($loanId) {
            $query->where('id', $loanId);
        }

        $query->orderBy('id')->chunkById(50, function ($loans) use (
            $throughDate,
            $fromDate,
            $engineEffective,
            $allowHistoricalBackfill,
            &$stats
        ) {
            foreach ($loans as $loan) {
                $stats['loans_scanned']++;
                try {
                    $result = $this->accrueLoan($loan, $throughDate, $fromDate, $engineEffective, $allowHistoricalBackfill);
                    $stats['accruals_created'] += $result['accruals_created'];
                    $stats['accruals_skipped_existing'] += $result['accruals_skipped_existing'];
                    $stats['accruals_adjusted'] += $result['accruals_adjusted'];
                    $stats['overdue_installments'] += $result['overdue_installments'];
                    $stats['total_arrears_charged'] = number_format(
                        (float) $stats['total_arrears_charged'] + (float) $result['total_charged'],
                        2,
                        '.',
                        ''
                    );
                    if ($result['classified_npl']) {
                        $stats['loans_classified_npl']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error('Arrears accrual failed for loan', [
                        'loan_id' => $loan->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $stats;
    }

    /**
     * @return array{accruals_created: int, accruals_skipped_existing: int, accruals_adjusted: int, overdue_installments: int, total_charged: string, classified_npl: bool}
     */
    public function accrueLoan(
        Loan $loan,
        Carbon $throughDate,
        ?Carbon $fromDate,
        ?Carbon $engineEffective = null,
        bool $allowHistoricalBackfill = false,
    ): array {
        $engineEffective ??= $this->resolveEngineEffectiveDate();
        $dailyFactor = ArrearRate::dailyFactor($loan->arrear_rate !== null ? (string) $loan->arrear_rate : null);

        if ((float) $dailyFactor <= 0) {
            return [
                'accruals_created' => 0,
                'accruals_skipped_existing' => 0,
                'accruals_adjusted' => 0,
                'overdue_installments' => 0,
                'total_charged' => '0.00',
                'classified_npl' => $this->classifyNplIfNeeded($loan, $throughDate),
            ];
        }

        $loan->loadMissing(['paymentSchedules']);

        if ($loan->paymentSchedules->isEmpty()) {
            return [
                'accruals_created' => 0,
                'accruals_skipped_existing' => 0,
                'accruals_adjusted' => 0,
                'overdue_installments' => 0,
                'total_charged' => '0.00',
                'classified_npl' => $this->classifyNplIfNeeded($loan, $throughDate),
            ];
        }

        $finalDueDate = $this->resolveFinalContractualDueDate($loan);
        $nplCutoff = $finalDueDate->copy()->addDays((int) config('arrears.npl_days_after_final_due', 90));

        $startDate = $this->resolveAccrualStartDate($loan, $fromDate, $engineEffective, $allowHistoricalBackfill);
        if ($startDate->gt($throughDate)) {
            return [
                'accruals_created' => 0,
                'accruals_skipped_existing' => 0,
                'accruals_adjusted' => 0,
                'overdue_installments' => 0,
                'total_charged' => '0.00',
                'classified_npl' => $this->classifyNplIfNeeded($loan, $throughDate),
            ];
        }

        $paymentTimeline = $this->buildPaymentTimeline($loan);
        $overdueInstallments = $loan->paymentSchedules
            ->filter(fn (LoanPaymentSchedule $schedule) => $schedule->due_date->lt($throughDate) && (float) $schedule->remaining_amount > 0)
            ->count();

        $created = 0;
        $skipped = 0;
        $totalCharged = '0.00';

        DB::transaction(function () use (
            $loan,
            $startDate,
            $throughDate,
            $nplCutoff,
            $dailyFactor,
            $paymentTimeline,
            $engineEffective,
            $allowHistoricalBackfill,
            &$created,
            &$skipped,
            &$totalCharged
        ) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $schedules = $lockedLoan->paymentSchedules()->orderBy('due_date')->orderBy('period_number')->get();

            for ($date = $startDate->copy(); $date->lte($throughDate); $date->addDay()) {
                if ($engineEffective && $date->lt($engineEffective) && ! $allowHistoricalBackfill) {
                    continue;
                }

                if ($date->gt($nplCutoff)) {
                    break;
                }

                $remainingBySchedule = $this->scheduleRemainingAsOf($schedules, $paymentTimeline, $date);

                foreach ($schedules as $schedule) {
                    if (! $this->scheduleEligibleForAccrualOnDate($schedule, $date)) {
                        continue;
                    }

                    $opening = ArrearRate::roundMoney((string) ($remainingBySchedule[$schedule->id] ?? 0));
                    if ((float) $opening <= 0) {
                        continue;
                    }

                    $exists = LoanArrearsAccrual::query()
                        ->where('loan_payment_schedule_id', $schedule->id)
                        ->whereDate('accrual_date', $date)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $charge = ArrearRate::calculateDailyCharge($opening, $dailyFactor);
                    if ((float) $charge <= 0) {
                        continue;
                    }

                    $daysOverdue = max(0, $schedule->due_date->diffInDays($date, false));

                    LoanArrearsAccrual::create([
                        'loan_id' => $lockedLoan->id,
                        'loan_payment_schedule_id' => $schedule->id,
                        'accrual_date' => $date->toDateString(),
                        'arrear_rate' => $lockedLoan->arrear_rate,
                        'opening_overdue_amount' => $opening,
                        'arrears_charge' => $charge,
                        'days_overdue_on_date' => $daysOverdue,
                        'metadata' => [
                            'daily_factor' => $dailyFactor,
                        ],
                    ]);

                    $created++;
                    $totalCharged = number_format((float) $totalCharged + (float) $charge, 2, '.', '');
                }

                $lockedLoan->arrears_last_accrual_date = $date->toDateString();
            }

            $lockedLoan->save();
        });

        $loan->refresh();

        return [
            'accruals_created' => $created,
            'accruals_skipped_existing' => $skipped,
            'accruals_adjusted' => 0,
            'overdue_installments' => $overdueInstallments,
            'total_charged' => $totalCharged,
            'classified_npl' => $this->classifyNplIfNeeded($loan, $throughDate),
        ];
    }

    /**
     * Recalculate same-day accrual rows after a payment posts (e.g. cron ran before the payment).
     */
    public function reconcileAccrualsAfterPayment(Loan $loan, Carbon $paymentEffectiveDate): int
    {
        $paymentEffectiveDate = $paymentEffectiveDate->copy()->startOfDay();
        $dailyFactor = ArrearRate::dailyFactor($loan->arrear_rate !== null ? (string) $loan->arrear_rate : null);
        if ((float) $dailyFactor <= 0) {
            return 0;
        }

        $loan->loadMissing(['paymentSchedules']);
        if ($loan->paymentSchedules->isEmpty()) {
            return 0;
        }

        if ($paymentEffectiveDate->gt($this->resolveNplCutoffDate($loan))) {
            return 0;
        }

        $engineEffective = $this->resolveEngineEffectiveDate();
        if ($engineEffective && $paymentEffectiveDate->lt($engineEffective)) {
            return 0;
        }

        $paymentTimeline = $this->buildPaymentTimeline($loan);
        $schedules = $loan->paymentSchedules()->orderBy('due_date')->orderBy('period_number')->get();
        $remainingBySchedule = $this->scheduleRemainingAsOf($schedules, $paymentTimeline, $paymentEffectiveDate);

        $adjusted = 0;

        DB::transaction(function () use (
            $loan,
            $schedules,
            $paymentEffectiveDate,
            $dailyFactor,
            $remainingBySchedule,
            &$adjusted
        ) {
            foreach ($schedules as $schedule) {
                if (! $this->scheduleEligibleForAccrualOnDate($schedule, $paymentEffectiveDate)) {
                    continue;
                }

                $accrual = LoanArrearsAccrual::query()
                    ->where('loan_payment_schedule_id', $schedule->id)
                    ->whereDate('accrual_date', $paymentEffectiveDate)
                    ->lockForUpdate()
                    ->first();

                if (! $accrual) {
                    continue;
                }

                $opening = ArrearRate::roundMoney((string) ($remainingBySchedule[$schedule->id] ?? 0));
                $newCharge = (float) $opening <= 0
                    ? '0.00'
                    : ArrearRate::calculateDailyCharge($opening, $dailyFactor);

                if (
                    abs((float) $accrual->opening_overdue_amount - (float) $opening) < 0.001
                    && abs((float) $accrual->arrears_charge - (float) $newCharge) < 0.001
                ) {
                    continue;
                }

                $metadata = array_merge($accrual->metadata ?? [], [
                    'adjusted_at' => now()->toIso8601String(),
                    'adjustment_reason' => 'payment_posted_same_day',
                    'previous_opening_overdue_amount' => $accrual->opening_overdue_amount,
                    'previous_arrears_charge' => $accrual->arrears_charge,
                ]);

                $accrual->update([
                    'opening_overdue_amount' => $opening,
                    'arrears_charge' => $newCharge,
                    'metadata' => $metadata,
                ]);

                $adjusted++;
            }
        });

        return $adjusted;
    }

    public function resolveFinalContractualDueDate(Loan $loan): Carbon
    {
        $maxDue = $loan->paymentSchedules()->max('due_date');

        if ($maxDue) {
            return Carbon::parse($maxDue)->startOfDay();
        }

        return Carbon::parse($loan->loan_end_date)->startOfDay();
    }

    public function resolveNplCutoffDate(Loan $loan): Carbon
    {
        return $this->resolveFinalContractualDueDate($loan)
            ->copy()
            ->addDays((int) config('arrears.npl_days_after_final_due', 90));
    }

    public function resolveEngineEffectiveDate(): ?Carbon
    {
        $configured = config('arrears.engine_effective_date');
        if (! $configured) {
            return null;
        }

        return Carbon::parse($configured)->startOfDay();
    }

    public function accrueThroughDate(Loan $loan, Carbon $date): void
    {
        $this->accrueLoan($loan, $date->copy()->startOfDay(), null, null, false);
    }

    /**
     * Dry-run: daily accrual rows that the scheduler would create but have not been posted yet.
     *
     * @return array{
     *     through_date: string,
     *     start_date: string,
     *     missed_accrual_count: int,
     *     total_arrears_charge: string,
     *     has_missed_accruals: bool,
     *     engine_effective_date: ?string
     * }
     */
    public function previewMissedAccruals(Loan $loan, ?Carbon $throughDate = null): array
    {
        $timezone = config('arrears.timezone', 'Africa/Lusaka');
        $throughDate ??= Carbon::today($timezone)->startOfDay();
        $engineEffective = $this->resolveEngineEffectiveDate();
        $dailyFactor = ArrearRate::dailyFactor($loan->arrear_rate !== null ? (string) $loan->arrear_rate : null);

        $empty = [
            'through_date' => $throughDate->toDateString(),
            'start_date' => $throughDate->toDateString(),
            'missed_accrual_count' => 0,
            'total_arrears_charge' => '0.00',
            'has_missed_accruals' => false,
            'engine_effective_date' => $engineEffective?->toDateString(),
        ];

        if ((float) $dailyFactor <= 0) {
            return $empty;
        }

        $loan->loadMissing(['paymentSchedules']);
        if ($loan->paymentSchedules->isEmpty()) {
            return $empty;
        }

        $nplCutoff = $this->resolveNplCutoffDate($loan);
        $startDate = $this->resolveAccrualStartDate($loan, null, $engineEffective, false);
        if ($startDate->gt($throughDate)) {
            return array_merge($empty, ['start_date' => $startDate->toDateString()]);
        }

        $paymentTimeline = $this->buildPaymentTimeline($loan);
        $schedules = $loan->paymentSchedules->sortBy([
            ['due_date', 'asc'],
            ['period_number', 'asc'],
        ])->values();

        $missedCount = 0;
        $totalCharged = '0.00';

        for ($date = $startDate->copy(); $date->lte($throughDate); $date->addDay()) {
            if ($engineEffective && $date->lt($engineEffective)) {
                continue;
            }

            if ($date->gt($nplCutoff)) {
                break;
            }

            $remainingBySchedule = $this->scheduleRemainingAsOf($schedules, $paymentTimeline, $date);

            foreach ($schedules as $schedule) {
                if (! $this->scheduleEligibleForAccrualOnDate($schedule, $date)) {
                    continue;
                }

                $opening = ArrearRate::roundMoney((string) ($remainingBySchedule[$schedule->id] ?? 0));
                if ((float) $opening <= 0) {
                    continue;
                }

                $exists = LoanArrearsAccrual::query()
                    ->where('loan_payment_schedule_id', $schedule->id)
                    ->whereDate('accrual_date', $date)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $charge = ArrearRate::calculateDailyCharge($opening, $dailyFactor);
                if ((float) $charge <= 0) {
                    continue;
                }

                $missedCount++;
                $totalCharged = number_format((float) $totalCharged + (float) $charge, 2, '.', '');
            }
        }

        return [
            'through_date' => $throughDate->toDateString(),
            'start_date' => $startDate->toDateString(),
            'missed_accrual_count' => $missedCount,
            'total_arrears_charge' => $totalCharged,
            'has_missed_accruals' => $missedCount > 0,
            'engine_effective_date' => $engineEffective?->toDateString(),
        ];
    }

    protected function resolveAccrualStartDate(
        Loan $loan,
        ?Carbon $fromDate,
        ?Carbon $engineEffective,
        bool $allowHistoricalBackfill,
    ): Carbon {
        if ($fromDate) {
            return $fromDate->copy()->startOfDay();
        }

        if ($loan->arrears_last_accrual_date) {
            return Carbon::parse($loan->arrears_last_accrual_date)->addDay()->startOfDay();
        }

        if ($engineEffective && ! $allowHistoricalBackfill) {
            return $engineEffective->copy()->startOfDay();
        }

        $firstDue = $loan->paymentSchedules()->min('due_date');

        return $firstDue
            ? Carbon::parse($firstDue)->addDay()->startOfDay()
            : Carbon::parse($loan->loan_start_date)->startOfDay();
    }

    protected function scheduleEligibleForAccrualOnDate(LoanPaymentSchedule $schedule, Carbon $accrualDate): bool
    {
        return $accrualDate->gt($schedule->due_date);
    }

    /**
     * Remaining schedule amounts at the start of $asOfDate (schedule-applied payment amounts with effective date on or before $asOfDate, FIFO).
     *
     * @param  Collection<int, LoanPaymentSchedule>  $schedules
     * @return array<int, float>
     */
    public function scheduleRemainingAsOf(Collection $schedules, Collection $paymentTimeline, Carbon $asOfDate): array
    {
        $state = [];
        foreach ($schedules as $schedule) {
            $state[$schedule->id] = (float) $schedule->expected_amount;
        }

        foreach ($paymentTimeline as $event) {
            /** @var Carbon $effective */
            $effective = $event['effective_date'];
            if ($effective->gt($asOfDate)) {
                break;
            }

            $remaining = (float) $event['amount'];
            foreach ($schedules as $schedule) {
                if ($remaining <= 0) {
                    break;
                }
                if ($state[$schedule->id] <= 0) {
                    continue;
                }
                $apply = min($state[$schedule->id], $remaining);
                $state[$schedule->id] = round($state[$schedule->id] - $apply, 2);
                $remaining = round($remaining - $apply, 2);
            }
        }

        return $state;
    }

    /**
     * @return Collection<int, array{effective_date: Carbon, amount: float}>
     */
    protected function buildPaymentTimeline(Loan $loan): Collection
    {
        return $loan->loanRepayments()
            ->where('transaction_type', LoanRepayment::TRANSACTION_TYPE_PAYMENT)
            ->where('amount', '>', 0)
            ->with('repayment:id,processed_at')
            ->get()
            ->map(function (LoanRepayment $loanRepayment) {
                return [
                    'effective_date' => $loanRepayment->effectiveDate(),
                    'amount' => $loanRepayment->scheduleAppliedAmount(),
                    'created_at' => $loanRepayment->created_at,
                ];
            })
            ->sortBy([
                ['effective_date', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values();
    }

    protected function classifyNplIfNeeded(Loan $loan, Carbon $asOfDate): bool
    {
        $cutoff = $this->resolveNplCutoffDate($loan);
        if ($asOfDate->lte($cutoff)) {
            return false;
        }

        $hasUnpaid = $loan->paymentSchedules()->where('remaining_amount', '>', 0)->exists()
            || app(LoanArrearsSummaryService::class)->outstandingArrearsInterest($loan) > 0
            || (float) $loan->outstanding_balance > 0;

        if (! $hasUnpaid) {
            return false;
        }

        if ($loan->performance_status === 'npl') {
            return false;
        }

        $loan->performance_status = 'npl';
        if (! $loan->npl_at) {
            $loan->npl_at = $asOfDate->copy()->startOfDay();
        }
        $loan->save();

        return true;
    }
}
