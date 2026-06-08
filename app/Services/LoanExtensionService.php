<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\LoanPaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LoanExtensionService
{
    /**
     * Preview extension impact without persisting changes.
     *
     * @return array<string, mixed>
     */
    public function preview(Loan $loan, array $input): array
    {
        $loan->loadMissing(['loanRate.loanRateType', 'loanRepayments', 'paymentSchedules']);

        try {
            $this->assertLoanIsEligibleForExtension($loan);
        } catch (\Throwable $e) {
            return [
                'eligible' => false,
                'message' => $e->getMessage(),
            ];
        }

        if ((int) $loan->loanExtensions()->count() >= 3) {
            return [
                'eligible' => false,
                'message' => 'This loan has already reached the maximum of 3 extensions.',
            ];
        }

        $extensionType = (int) $input['extension_type'];
        $interestMode = (int) $input['interest_mode'];
        $periodValue = (int) $input['extension_period_value'];
        $periodUnit = (string) $input['extension_period_unit'];
        $interestValue = isset($input['interest_value']) ? (float) $input['interest_value'] : 0.0;
        $newInstallmentCount = isset($input['new_installment_count']) ? (int) $input['new_installment_count'] : null;

        if ($extensionType === LoanExtension::TYPE_RESTRUCTURE && (! $newInstallmentCount || $newInstallmentCount < 1)) {
            return [
                'eligible' => false,
                'message' => 'New installment count is required for restructure extensions.',
            ];
        }

        $unpaidSchedules = $loan->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('period_number')
            ->get();

        if ($unpaidSchedules->isEmpty()) {
            return [
                'eligible' => false,
                'message' => 'Loan has no unpaid installments to extend.',
            ];
        }

        $oldDueDate = $unpaidSchedules->max('due_date');
        $metrics = $this->calculateOutstandingMetrics($loan);
        $baseAmount = $metrics['remaining_principal'] > 0
            ? $metrics['remaining_principal']
            : $metrics['total_outstanding'];

        $interestComputation = $this->calculateExtensionInterest(
            $loan,
            $interestMode,
            $periodValue,
            $periodUnit,
            $interestValue,
            $baseAmount
        );

        $extensionInterest = $interestComputation['interest_amount'];
        $configuredRates = $this->resolveConfiguredRates($loan);

        $projectedOutstanding = match ($extensionType) {
            LoanExtension::TYPE_DUE_DATE_EXTENSION => round($metrics['total_outstanding'] + $extensionInterest, 2),
            LoanExtension::TYPE_INTEREST_ROLLOVER => $this->projectOutstandingAfterRollover($metrics, $extensionInterest),
            LoanExtension::TYPE_RESTRUCTURE => round($metrics['total_outstanding'] + $extensionInterest, 2),
            default => round($metrics['total_outstanding'], 2),
        };

        $newLastDueDate = $this->shiftDate(
            Carbon::parse($oldDueDate),
            $periodValue,
            $periodUnit
        );

        if ($extensionType === LoanExtension::TYPE_RESTRUCTURE && $newInstallmentCount) {
            $firstBaseDueDate = $unpaidSchedules->min('due_date');
            $firstDueDate = $this->shiftDate(
                $firstBaseDueDate ? Carbon::parse($firstBaseDueDate) : Carbon::today(),
                $periodValue,
                $periodUnit
            );
            $newLastDueDate = $this->shiftDate(
                $firstDueDate,
                ($newInstallmentCount - 1) * max(1, $periodValue),
                $periodUnit
            );
        }

        $perInstallment = ($extensionType === LoanExtension::TYPE_RESTRUCTURE && $newInstallmentCount)
            ? round($projectedOutstanding / $newInstallmentCount, 2)
            : null;

        return [
            'eligible' => true,
            'extension_type' => $extensionType,
            'extension_type_label' => LoanExtension::typeOptions()[$extensionType] ?? 'Unknown',
            'interest_mode' => $interestMode,
            'interest_mode_label' => LoanExtension::interestModeOptions()[$interestMode] ?? 'Unknown',
            'outstanding_before' => $metrics,
            'interest' => $interestComputation,
            'configured_rates' => $configuredRates,
            'projected' => [
                'extension_interest' => $extensionInterest,
                'current_outstanding' => round($metrics['total_outstanding'], 2),
                'projected_outstanding' => $projectedOutstanding,
                'unpaid_installment_count' => $unpaidSchedules->count(),
                'old_last_due_date' => Carbon::parse($oldDueDate)->toDateString(),
                'new_last_due_date' => $newLastDueDate->toDateString(),
                'extension_period' => $this->extensionPeriodToText($periodValue, $periodUnit),
                'per_installment' => $perInstallment,
                'new_installment_count' => $newInstallmentCount,
            ],
        ];
    }

    public function extend(Loan $loan, array $input, int $adminId): LoanExtension
    {
        return DB::transaction(function () use ($loan, $input, $adminId): LoanExtension {
            /** @var Loan $lockedLoan */
            $lockedLoan = Loan::query()
                ->whereKey($loan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedLoan->loadMissing(['loanRate', 'loanRepayments', 'paymentSchedules']);

            $this->assertLoanIsEligibleForExtension($lockedLoan);

            $existingExtensions = (int) $lockedLoan->loanExtensions()->count();
            if ($existingExtensions >= 3) {
                throw new RuntimeException('This loan has already reached the maximum of 3 extensions.');
            }

            $extensionType = (int) $input['extension_type'];
            $interestMode = (int) $input['interest_mode'];
            $periodValue = (int) $input['extension_period_value'];
            $periodUnit = (string) $input['extension_period_unit'];
            $interestValue = isset($input['interest_value']) ? (float) $input['interest_value'] : 0.0;
            $newInstallmentCount = isset($input['new_installment_count']) ? (int) $input['new_installment_count'] : null;

            $unpaidSchedules = $lockedLoan->paymentSchedules()
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('period_number')
                ->get();

            if ($unpaidSchedules->isEmpty()) {
                throw new RuntimeException('Loan has no unpaid installments to extend.');
            }

            $oldDueDate = $unpaidSchedules->max('due_date');
            $metrics = $this->calculateOutstandingMetrics($lockedLoan);
            $interestComputation = $this->calculateExtensionInterest(
                $lockedLoan,
                $interestMode,
                $periodValue,
                $periodUnit,
                $interestValue,
                $metrics['remaining_principal'] > 0 ? $metrics['remaining_principal'] : $metrics['total_outstanding']
            );

            $extension = LoanExtension::query()->create([
                'loan_id' => $lockedLoan->id,
                'extension_type' => $extensionType,
                'interest_mode' => $interestMode,
                'interest_rate' => $interestComputation['interest_rate'],
                'interest_amount' => $interestComputation['interest_amount'],
                'old_due_date' => $oldDueDate,
                'new_due_date' => null,
                'extension_period' => $this->extensionPeriodToText($periodValue, $periodUnit),
                'notes' => $input['notes'] ?? null,
                'created_by' => $adminId,
                'metadata' => [
                    'outstanding_before' => $metrics,
                    'extension_sequence' => $existingExtensions + 1,
                    'new_installment_count' => $newInstallmentCount,
                ],
            ]);

            $typeResult = match ($extensionType) {
                LoanExtension::TYPE_DUE_DATE_EXTENSION => $this->applyDueDateExtension(
                    $unpaidSchedules,
                    $extension,
                    $periodValue,
                    $periodUnit,
                    $interestComputation['interest_amount']
                ),
                LoanExtension::TYPE_INTEREST_ROLLOVER => $this->applyInterestRollover(
                    $unpaidSchedules,
                    $extension,
                    $periodValue,
                    $periodUnit,
                    $interestComputation['interest_amount'],
                    $metrics
                ),
                LoanExtension::TYPE_RESTRUCTURE => $this->applyRestructure(
                    $lockedLoan,
                    $unpaidSchedules,
                    $extension,
                    $periodValue,
                    $periodUnit,
                    $interestComputation['interest_amount'],
                    $metrics,
                    $newInstallmentCount
                ),
                default => throw new InvalidArgumentException('Invalid extension type selected.'),
            };

            $lockedLoan->syncOutstandingBalanceFromSchedule();
            $lockedLoan->refresh();

            $lockedLoan->interest_accrued = round((float) $lockedLoan->interest_accrued + $interestComputation['interest_amount'], 2);
            $lockedLoan->total_amount = round((float) $lockedLoan->amount_paid + (float) $lockedLoan->outstanding_balance, 2);
            $lockedLoan->loan_end_date = $typeResult['new_due_date'];

            if ($extensionType === LoanExtension::TYPE_RESTRUCTURE && !empty($typeResult['first_new_due_date'])) {
                $lockedLoan->first_payment_date = $typeResult['first_new_due_date'];
                if ($newInstallmentCount) {
                    $lockedLoan->tenure_months = $newInstallmentCount;
                }
            }

            $metadata = $lockedLoan->metadata ?? [];
            $metadata['last_extension'] = [
                'id' => $extension->id,
                'type' => $extension->extension_type,
                'at' => now()->toIso8601String(),
                'admin_id' => $adminId,
            ];
            $lockedLoan->metadata = $metadata;

            $lockedLoan->save();

            $updatedMetadata = $extension->metadata ?? [];
            $updatedMetadata['outstanding_after'] = $this->calculateOutstandingMetrics($lockedLoan);
            $updatedMetadata['schedule_update'] = $typeResult['metadata'] ?? [];
            $updatedMetadata['interest'] = $interestComputation;

            $extension->update([
                'new_due_date' => $typeResult['new_due_date'],
                'metadata' => $updatedMetadata,
            ]);

            return $extension->fresh(['creator', 'loan']);
        });
    }

    private function assertLoanIsEligibleForExtension(Loan $loan): void
    {
        $blockedStatuses = ['closed', 'defaulted', 'written_off', 'settled', 'completed', 'cancelled'];

        if ($loan->status !== 'active') {
            throw new RuntimeException('Only active loans can be extended.');
        }

        if (in_array($loan->status, $blockedStatuses, true)) {
            throw new RuntimeException('This loan status does not allow extensions.');
        }

        if ((float) $loan->outstanding_balance <= 0) {
            throw new RuntimeException('Fully paid loans cannot be extended.');
        }
    }

    /**
     * @return array{remaining_principal: float, remaining_interest: float, penalties: float, total_outstanding: float, total_paid: float, principal_paid: float, interest_paid: float}
     */
    private function calculateOutstandingMetrics(Loan $loan): array
    {
        $principalPaid = (float) $loan->loanRepayments()->sum('principal_amount');
        $interestPaid = (float) $loan->loanRepayments()->sum('interest_amount');
        $totalPaid = (float) $loan->amount_paid;

        $remainingPrincipal = max(0, (float) $loan->principal_amount - $principalPaid);
        $remainingInterest = max(0, (float) $loan->interest_accrued - $interestPaid);
        $penalties = 0.0;

        return [
            'remaining_principal' => round($remainingPrincipal, 2),
            'remaining_interest' => round($remainingInterest, 2),
            'penalties' => round($penalties, 2),
            'total_outstanding' => round((float) $loan->outstanding_balance, 2),
            'total_paid' => round($totalPaid, 2),
            'principal_paid' => round($principalPaid, 2),
            'interest_paid' => round($interestPaid, 2),
        ];
    }

    /**
     * @return array{interest_rate: float|null, interest_amount: float, period_days: int, base_amount: float}
     */
    private function calculateExtensionInterest(
        Loan $loan,
        int $interestMode,
        int $periodValue,
        string $periodUnit,
        float $interestValue,
        float $baseAmount
    ): array {
        $periodDays = $periodUnit === 'months'
            ? max(1, $periodValue) * 30
            : max(1, $periodValue);

        $baseAmount = max(0, $baseAmount);

        if ($interestMode === LoanExtension::INTEREST_MODE_FIXED_AMOUNT) {
            return [
                'interest_rate' => null,
                'interest_amount' => round(max(0, $interestValue), 2),
                'period_days' => $periodDays,
                'base_amount' => round($baseAmount, 2),
            ];
        }

        if ($interestMode === LoanExtension::INTEREST_MODE_CUSTOM_RATE) {
            $rate = max(0, $interestValue);

            return [
                'interest_rate' => round($rate, 6),
                'interest_amount' => round(($baseAmount * $rate) / 100, 2),
                'period_days' => $periodDays,
                'base_amount' => round($baseAmount, 2),
            ];
        }

        if ($interestMode !== LoanExtension::INTEREST_MODE_CONFIGURED_RATE) {
            throw new InvalidArgumentException('Invalid interest mode selected.');
        }

        $rates = $this->resolveConfiguredRates($loan);
        $dailyRate = $rates['daily_rate'];
        $weeklyRate = $rates['weekly_rate'];
        $accrualPeriod = $rates['accrual_period'];

        $interestAmount = 0.0;
        $effectiveRate = 0.0;

        if ($dailyRate > 0 && ($accrualPeriod !== 'weekly' || $weeklyRate <= 0)) {
            $interestAmount = $baseAmount * $dailyRate * $periodDays;
            $effectiveRate = $dailyRate * 100;
        } elseif ($weeklyRate > 0) {
            $weeks = (int) ceil($periodDays / 7);
            $interestAmount = $baseAmount * $weeklyRate * $weeks;
            $effectiveRate = $weeklyRate * 100;
        }

        return [
            'interest_rate' => round($effectiveRate, 6),
            'interest_amount' => round(max(0, $interestAmount), 2),
            'period_days' => $periodDays,
            'base_amount' => round($baseAmount, 2),
            'rate_source' => $rates['source'],
            'accrual_period' => $accrualPeriod,
        ];
    }

    /**
     * @return array{remaining_principal: float, remaining_interest: float, penalties: float, total_outstanding: float, total_paid: float, principal_paid: float, interest_paid: float}
     */
    private function projectOutstandingAfterRollover(array $metrics, float $extensionInterest): float
    {
        $totalPaid = $metrics['total_paid'];
        $interestShortfall = max(0, $extensionInterest - $totalPaid);
        $principalReduction = min($metrics['remaining_principal'], max(0, $totalPaid - $extensionInterest));
        $remainingPrincipal = max(0, $metrics['remaining_principal'] - $principalReduction);

        return round(max(0, $remainingPrincipal + $metrics['remaining_interest'] + $metrics['penalties'] + $interestShortfall), 2);
    }

    /**
     * @return array{daily_rate: float, weekly_rate: float, accrual_period: string, source: string, display_daily_rate: string|null, display_weekly_rate: string|null}
     */
    private function resolveConfiguredRates(Loan $loan): array
    {
        $loan->loadMissing(['loanRate.loanRateType']);

        $dailyRate = (float) ($loan->daily_rate ?? 0);
        $weeklyRate = (float) ($loan->weekly_rate ?? 0);
        $source = 'loan_snapshot';

        if ($dailyRate <= 0 && $weeklyRate <= 0 && $loan->loanRate) {
            $effectiveDaily = $loan->loanRate->effectiveDailyRate();
            $dailyRate = $effectiveDaily !== null ? (float) $effectiveDaily : 0.0;
            $weeklyRate = (float) ($loan->loanRate->weekly_rate ?? 0);
            $source = 'loan_rate';
        }

        $accrualPeriod = (string) ($loan->accrual_period
            ?? $loan->loanRate?->loanRateType?->accrual_period
            ?? 'daily');

        return [
            'daily_rate' => $dailyRate,
            'weekly_rate' => $weeklyRate,
            'accrual_period' => $accrualPeriod,
            'source' => $source,
            'display_daily_rate' => $dailyRate > 0 ? number_format($dailyRate, 8) : null,
            'display_weekly_rate' => $weeklyRate > 0 ? number_format($weeklyRate, 8) : null,
        ];
    }

    /**
     * @return array{new_due_date: Carbon, metadata: array<string, mixed>, first_new_due_date?: Carbon}
     */
    private function applyDueDateExtension(
        Collection $unpaidSchedules,
        LoanExtension $extension,
        int $periodValue,
        string $periodUnit,
        float $extensionInterest
    ): array {
        $shiftedSchedules = $this->shiftSchedules($unpaidSchedules, $periodValue, $periodUnit, $extension->id);

        /** @var LoanPaymentSchedule|null $lastSchedule */
        $lastSchedule = $shiftedSchedules->last();
        if ($lastSchedule && $extensionInterest > 0) {
            $lastSchedule->expected_amount = round((float) $lastSchedule->expected_amount + $extensionInterest, 2);
            $lastSchedule->remaining_amount = round((float) $lastSchedule->remaining_amount + $extensionInterest, 2);
            $lastSchedule->save();
            $lastSchedule->updateStatus();
        }

        $newDueDate = Carbon::parse($shiftedSchedules->max('due_date'));

        return [
            'new_due_date' => $newDueDate,
            'metadata' => [
                'updated_schedule_ids' => $shiftedSchedules->pluck('id')->all(),
                'extension_interest_added_to_schedule_id' => $lastSchedule?->id,
            ],
        ];
    }

    /**
     * @param array{remaining_principal: float, remaining_interest: float, penalties: float, total_outstanding: float, total_paid: float, principal_paid: float, interest_paid: float} $metrics
     * @return array{new_due_date: Carbon, metadata: array<string, mixed>}
     */
    private function applyInterestRollover(
        Collection $unpaidSchedules,
        LoanExtension $extension,
        int $periodValue,
        string $periodUnit,
        float $extensionInterest,
        array $metrics
    ): array {
        $shiftedSchedules = $this->shiftSchedules($unpaidSchedules, $periodValue, $periodUnit, $extension->id);

        $totalPaid = $metrics['total_paid'];
        $interestCovered = min($totalPaid, $extensionInterest);
        $principalReduction = min($metrics['remaining_principal'], max(0, $totalPaid - $extensionInterest));
        $remainingPrincipal = max(0, $metrics['remaining_principal'] - $principalReduction);
        $interestShortfall = max(0, $extensionInterest - $totalPaid);

        $newOutstanding = max(0, $remainingPrincipal + $metrics['remaining_interest'] + $metrics['penalties'] + $interestShortfall);
        $this->redistributeRemainingBalance($shiftedSchedules, $newOutstanding);

        $newDueDate = Carbon::parse($shiftedSchedules->max('due_date'));

        return [
            'new_due_date' => $newDueDate,
            'metadata' => [
                'updated_schedule_ids' => $shiftedSchedules->pluck('id')->all(),
                'interest_required' => round($extensionInterest, 2),
                'interest_covered_from_paid' => round($interestCovered, 2),
                'principal_reduction_from_excess' => round($principalReduction, 2),
                'target_outstanding_after_rollover' => round($newOutstanding, 2),
            ],
        ];
    }

    /**
     * @param array{remaining_principal: float, remaining_interest: float, penalties: float, total_outstanding: float, total_paid: float, principal_paid: float, interest_paid: float} $metrics
     * @return array{new_due_date: Carbon, first_new_due_date: Carbon, metadata: array<string, mixed>}
     */
    private function applyRestructure(
        Loan $loan,
        Collection $unpaidSchedules,
        LoanExtension $extension,
        int $periodValue,
        string $periodUnit,
        float $extensionInterest,
        array $metrics,
        ?int $newInstallmentCount
    ): array {
        if (!$newInstallmentCount || $newInstallmentCount < 1) {
            throw new InvalidArgumentException('New installment count is required for loan restructure.');
        }

        $unpaidScheduleIds = $unpaidSchedules->pluck('id')->all();

        LoanPaymentSchedule::withoutGlobalScope('non_restructured')
            ->whereIn('id', $unpaidScheduleIds)
            ->update([
                'is_restructured' => true,
                'restructured_at' => now(),
                'loan_extension_id' => $extension->id,
            ]);

        $targetOutstanding = max(0, $metrics['total_outstanding'] + $extensionInterest);

        $firstBaseDueDate = $unpaidSchedules->min('due_date');
        $firstDueDate = $this->shiftDate(
            $firstBaseDueDate ? Carbon::parse($firstBaseDueDate) : Carbon::today(),
            $periodValue,
            $periodUnit
        );

        $nextPeriodNumber = (int) (LoanPaymentSchedule::withoutGlobalScope('non_restructured')
            ->where('loan_id', $loan->id)
            ->max('period_number') ?? 0) + 1;

        $perInstallment = $newInstallmentCount > 0
            ? round($targetOutstanding / $newInstallmentCount, 2)
            : 0.0;

        $remaining = $targetOutstanding;
        $createdScheduleIds = [];

        for ($i = 0; $i < $newInstallmentCount; $i++) {
            $dueDate = $this->shiftDate($firstDueDate, $i * max(1, $periodValue), $periodUnit);
            $isLast = $i === ($newInstallmentCount - 1);
            $installmentAmount = $isLast ? round($remaining, 2) : min($remaining, $perInstallment);

            $schedule = LoanPaymentSchedule::query()->create([
                'loan_id' => $loan->id,
                'period_number' => $nextPeriodNumber + $i,
                'due_date' => $dueDate,
                'expected_amount' => $installmentAmount,
                'amount_paid' => 0,
                'remaining_amount' => $installmentAmount,
                'status' => 'upcoming',
                'days_overdue' => 0,
                'loan_extension_id' => $extension->id,
            ]);

            $createdScheduleIds[] = $schedule->id;
            $remaining = round($remaining - $installmentAmount, 2);
            $schedule->updateStatus();
        }

        $newDueDate = $this->shiftDate($firstDueDate, ($newInstallmentCount - 1) * max(1, $periodValue), $periodUnit);

        return [
            'new_due_date' => $newDueDate,
            'first_new_due_date' => $firstDueDate,
            'metadata' => [
                'restructured_schedule_ids' => $unpaidScheduleIds,
                'new_schedule_ids' => $createdScheduleIds,
                'new_installment_count' => $newInstallmentCount,
                'target_outstanding_after_restructure' => round($targetOutstanding, 2),
            ],
        ];
    }

    private function extensionPeriodToText(int $periodValue, string $periodUnit): string
    {
        $safeValue = max(1, $periodValue);
        $unit = $periodUnit === 'months' ? 'month' : 'day';
        $plural = $safeValue === 1 ? '' : 's';

        return "{$safeValue} {$unit}{$plural}";
    }

    private function shiftDate(Carbon $date, int $value, string $unit): Carbon
    {
        if ($value <= 0) {
            return $date->copy();
        }

        $safeValue = $value;

        return $unit === 'months'
            ? $date->copy()->addMonthsNoOverflow($safeValue)
            : $date->copy()->addDays($safeValue);
    }

    /**
     * @return Collection<int, LoanPaymentSchedule>
     */
    private function shiftSchedules(Collection $schedules, int $periodValue, string $periodUnit, int $extensionId): Collection
    {
        return $schedules->map(function (LoanPaymentSchedule $schedule) use ($periodValue, $periodUnit, $extensionId) {
            $schedule->due_date = $this->shiftDate(Carbon::parse($schedule->due_date), $periodValue, $periodUnit);
            $schedule->loan_extension_id = $extensionId;
            $schedule->save();
            $schedule->updateStatus();

            return $schedule;
        });
    }

    private function redistributeRemainingBalance(Collection $schedules, float $targetOutstanding): void
    {
        $count = $schedules->count();
        if ($count === 0) {
            return;
        }

        $perInstallment = $count > 0 ? round($targetOutstanding / $count, 2) : 0.0;
        $remaining = round($targetOutstanding, 2);

        $schedules->values()->each(function (LoanPaymentSchedule $schedule, int $index) use ($count, $perInstallment, &$remaining): void {
            $isLast = $index === ($count - 1);
            $newRemaining = $isLast ? round($remaining, 2) : min($remaining, $perInstallment);

            $schedule->remaining_amount = $newRemaining;
            $schedule->expected_amount = round((float) $schedule->amount_paid + $newRemaining, 2);
            $schedule->save();
            $schedule->updateStatus();

            $remaining = round($remaining - $newRemaining, 2);
        });
    }
}
