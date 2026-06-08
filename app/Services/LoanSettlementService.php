<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LoanSettlementService
{
    private const MONEY_SCALE = 2;

    private const CALC_SCALE = 12;

    public function quoteSettlement(Loan $loan, Carbon|string|null $settlementDate = null): array
    {
        $this->assertLoanCanBeQuoted($loan);

        $settlementDate = $this->resolveSettlementDate($loan, $settlementDate);
        $behavior = $this->resolveSettlementBehavior($loan);

        $interestBooked = $this->getInterestBookedAmount($loan);
        $earnedInterest = $this->calculateEarnedInterest($loan, $settlementDate);
        $rebate = $this->calculateUnearnedInterestRebate($loan, $settlementDate);

        $paid = $this->sumPaidComponents($loan);
        $remaining = $this->calculateRemainingComponents($loan, $settlementDate, $paid);

        $payoff = $this->calculatePayoffAmount($loan, $settlementDate);

        $notes = $this->buildQuoteNotes($loan, $behavior, $settlementDate, $earnedInterest, $rebate);

        return [
            'loan_id' => $loan->id,
            'settlement_date' => $settlementDate->toDateString(),
            'principal_remaining' => $remaining['principal_remaining'],
            'processing_fee_remaining' => $remaining['processing_fee_remaining'],
            'interest_booked' => $interestBooked,
            'interest_earned' => $earnedInterest,
            'interest_paid' => $paid['interest_paid'],
            'interest_remaining_earned' => $remaining['interest_remaining_earned'],
            'unearned_interest_rebate' => $rebate,
            'current_outstanding_balance' => $this->formatMoney((string) $loan->outstanding_balance),
            'payoff_amount' => $payoff,
            'interest_behavior' => $behavior,
            'processing_fee_refundable' => false,
            'notes' => $notes,
        ];
    }

    public function calculateEarnedInterest(Loan $loan, Carbon $settlementDate): string
    {
        $behavior = $this->resolveSettlementBehavior($loan);

        if ($behavior === 'legacy') {
            return $this->formatMoney((string) $loan->interest_accrued);
        }

        if ($behavior === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            $posted = $this->formatMoney((string) $loan->interest_accrued);
            $projected = $this->calculateProjectedDailyAccrualThroughDate($loan, $settlementDate);

            return $this->formatMoney($this->bcAdd($posted, $projected));
        }

        return $this->calculateUpfrontEarnedInterest($loan, $settlementDate);
    }

    public function calculateUnearnedInterestRebate(Loan $loan, Carbon $settlementDate): string
    {
        $behavior = $this->resolveSettlementBehavior($loan);

        if ($behavior !== Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return '0.00';
        }

        $booked = $this->getInterestBookedAmount($loan);
        $earned = $this->calculateUpfrontEarnedInterest($loan, $settlementDate);
        $rebate = $this->bcSub($booked, $earned);

        if ($this->bcComp($rebate, '0') < 0) {
            return '0.00';
        }

        return $this->formatMoney($rebate);
    }

    public function calculatePayoffAmount(Loan $loan, Carbon $settlementDate): string
    {
        $behavior = $this->resolveSettlementBehavior($loan);

        if ($behavior === 'legacy') {
            return $this->formatMoney((string) max(0, (float) $loan->outstanding_balance));
        }

        $paid = $this->sumPaidComponents($loan);
        $remaining = $this->calculateRemainingComponents($loan, $settlementDate, $paid);

        $payoff = $this->bcAdd(
            $this->bcAdd($remaining['principal_remaining'], $remaining['processing_fee_remaining']),
            $remaining['interest_remaining_earned']
        );

        if ($this->bcComp($payoff, '0') < 0) {
            return '0.00';
        }

        return $this->formatMoney($payoff);
    }

    /**
     * @param  array{
     *     amount: float|int|string,
     *     settlement_date?: Carbon|string|null,
     *     channel_id?: int,
     *     phone_number?: string|null,
     *     notes?: string|null,
     *     allow_partial?: bool,
     *     repayment_id?: int|null
     * }  $payload
     */
    public function applySettlement(Loan $loan, array $payload): LoanRepayment
    {
        return DB::transaction(function () use ($loan, $payload): LoanRepayment {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status === 'settled') {
                throw new RuntimeException('Loan is already settled.');
            }

            if (! in_array($loan->status, ['active', 'approved'], true)) {
                throw new RuntimeException('Loan must be active or approved to settle.');
            }

            if ((float) $loan->outstanding_balance <= 0) {
                throw new RuntimeException('Loan has no outstanding balance to settle.');
            }

            $settlementDate = $this->resolveSettlementDate($loan, $payload['settlement_date'] ?? null);
            $quoteBefore = $this->quoteSettlement($loan, $settlementDate);

            $amountReceived = $this->normalizeMoney($payload['amount'] ?? null, 'amount');
            $payoffQuoted = $quoteBefore['payoff_amount'];
            $allowPartial = (bool) ($payload['allow_partial'] ?? false);

            if (! $allowPartial && $this->bcComp($amountReceived, $payoffQuoted) < 0) {
                throw new InvalidArgumentException(
                    "Settlement amount {$amountReceived} is less than quoted payoff {$payoffQuoted}."
                );
            }

            $outstandingBefore = (float) $loan->outstanding_balance;

            if ($this->resolveSettlementBehavior($loan) === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
                $this->accrueInterestThroughDate($loan, $settlementDate);
                $loan->refresh();
            }

            if ($this->resolveSettlementBehavior($loan) === Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
                $this->applyUpfrontRebateAdjustment($loan, $settlementDate);
                $loan->refresh();
            }

            $quote = $this->quoteSettlement($loan, $settlementDate);
            $payoff = $quote['payoff_amount'];
            $rebateAmount = $quote['unearned_interest_rebate'];
            $appliedAmount = $this->formatMoney(
                $this->bcComp($amountReceived, $payoff) > 0 ? $payoff : $amountReceived
            );

            $paid = $this->sumPaidComponents($loan);
            $remaining = $this->calculateRemainingComponents($loan, $settlementDate, $paid);

            $repayment = $this->resolveRepaymentRecord($loan, $payload, $appliedAmount, $settlementDate, $quote);

            $principalApplied = $remaining['principal_remaining'];
            $feeApplied = $remaining['processing_fee_remaining'];
            $interestApplied = $remaining['interest_remaining_earned'];

            $loanRepayment = LoanRepayment::create([
                'repayment_id' => $repayment->id,
                'loan_id' => $loan->id,
                'amount' => (float) $appliedAmount,
                'principal_amount' => (float) $principalApplied,
                'interest_amount' => (float) $interestApplied,
                'processing_fee_amount' => (float) $feeApplied,
                'outstanding_balance_before' => $outstandingBefore,
                'outstanding_balance_after' => 0,
                'notes' => $payload['notes'] ?? 'Early loan settlement',
                'metadata' => [
                    'settlement' => true,
                    'settlement_date' => $settlementDate->toDateString(),
                    'quoted_payoff' => $payoff,
                    'amount_received' => $amountReceived,
                    'unearned_interest_rebate' => $quote['unearned_interest_rebate'],
                    'interest_earned_at_settlement' => $quote['interest_earned'],
                    'interest_behavior' => $quote['interest_behavior'],
                ],
            ]);

            if ($loan->paymentSchedules()->exists()) {
                $this->closeRemainingSchedules($loan, $settlementDate);
            }

            $loan->update([
                'amount_paid' => $this->formatMoney(
                    $this->bcAdd((string) $loan->amount_paid, $appliedAmount)
                ),
                'outstanding_balance' => 0,
                'settlement_amount' => (float) $appliedAmount,
                'settlement_date' => $settlementDate,
                'rebate_amount' => (float) $rebateAmount,
                'loan_settled_date' => $settlementDate,
                'status' => 'settled',
                'metadata' => array_merge($loan->metadata ?? [], [
                    'settlement' => [
                        'settlement_date' => $settlementDate->toDateString(),
                        'payoff_amount' => $payoff,
                        'rebate_amount' => $quote['unearned_interest_rebate'],
                        'interest_earned' => $quote['interest_earned'],
                        'interest_booked_at_settlement' => $quote['interest_booked'],
                        'closed_projected_schedule' => $loan->scheduleUsesProjectedInterest(),
                        'loan_repayment_id' => $loanRepayment->id,
                    ],
                ]),
            ]);

            return $loanRepayment;
        });
    }

    private function assertLoanCanBeQuoted(Loan $loan): void
    {
        if ($loan->status === 'settled') {
            throw new RuntimeException('Loan is already settled.');
        }

        if (! in_array($loan->status, ['active', 'approved'], true)) {
            throw new RuntimeException('Loan must be active or approved to quote settlement.');
        }
    }

    private function resolveSettlementDate(Loan $loan, Carbon|string|null $settlementDate): Carbon
    {
        $date = $settlementDate === null
            ? Carbon::today()
            : ($settlementDate instanceof CarbonInterface
                ? $settlementDate->copy()
                : Carbon::parse($settlementDate));

        $date = $date->startOfDay();

        if ($loan->loan_start_date && $date->lt($loan->loan_start_date)) {
            throw new InvalidArgumentException('Settlement date cannot be before loan start date.');
        }

        if ($loan->loan_end_date && $date->gt($loan->loan_end_date)) {
            throw new InvalidArgumentException('Settlement date cannot be after loan end date.');
        }

        return $date;
    }

    private function resolveSettlementBehavior(Loan $loan): string
    {
        if (in_array($loan->interest_behavior, [
            Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
        ], true)) {
            return $loan->interest_behavior;
        }

        return 'legacy';
    }

    /**
     * @return array{principal_paid: string, interest_paid: string, processing_fee_paid: string}
     */
    private function sumPaidComponents(Loan $loan): array
    {
        return [
            'principal_paid' => $this->formatMoney((string) $loan->loanRepayments()->sum('principal_amount')),
            'interest_paid' => $this->formatMoney((string) $loan->loanRepayments()->sum('interest_amount')),
            'processing_fee_paid' => $this->formatMoney((string) $loan->loanRepayments()->sum('processing_fee_amount')),
        ];
    }

    /**
     * @param  array{principal_paid: string, interest_paid: string, processing_fee_paid: string}  $paid
     * @return array{
     *     principal_remaining: string,
     *     processing_fee_remaining: string,
     *     interest_remaining_earned: string
     * }
     */
    private function calculateRemainingComponents(Loan $loan, Carbon $settlementDate, array $paid): array
    {
        $principal = $this->formatMoney((string) $loan->principal_amount);
        $fee = $this->formatMoney((string) $loan->processing_fee);
        $earned = $this->calculateEarnedInterest($loan, $settlementDate);

        $principalRemaining = $this->bcSub($principal, $paid['principal_paid']);
        $feeRemaining = $this->bcSub($fee, $paid['processing_fee_paid']);
        $interestRemaining = $this->bcSub($earned, $paid['interest_paid']);

        return [
            'principal_remaining' => $this->formatMoney($this->bcMax($principalRemaining, '0')),
            'processing_fee_remaining' => $this->formatMoney($this->bcMax($feeRemaining, '0')),
            'interest_remaining_earned' => $this->formatMoney($this->bcMax($interestRemaining, '0')),
        ];
    }

    private function getInterestBookedAmount(Loan $loan): string
    {
        if ($this->resolveSettlementBehavior($loan) === Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            $fromMeta = data_get($loan->metadata, 'projected_interest');
            if ($fromMeta !== null) {
                return $this->formatMoney((string) $fromMeta);
            }

            $plan = $loan->getSchedulePlan();

            return $this->formatMoney((string) $plan['interest']);
        }

        return $this->formatMoney((string) $loan->interest_accrued);
    }

    private function calculateUpfrontEarnedInterest(Loan $loan, Carbon $settlementDate): string
    {
        $booked = $this->getInterestBookedAmount($loan);
        $termDays = $this->resolveTermDays($loan);

        if ($this->bcComp($booked, '0') <= 0 || $termDays < 1) {
            return '0.00';
        }

        $elapsedDays = min(
            $termDays,
            max(1, $loan->loan_start_date->diffInDays($settlementDate) + 1)
        );

        $earned = $this->bcDiv(
            $this->bcMul($booked, (string) $elapsedDays),
            (string) $termDays
        );

        return $this->formatMoney($this->bcMin($earned, $booked));
    }

    private function calculateProjectedDailyAccrualThroughDate(Loan $loan, Carbon $settlementDate): string
    {
        if ($this->resolveSettlementBehavior($loan) !== Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            return '0.00';
        }

        if (! $loan->daily_rate || (float) $loan->daily_rate <= 0) {
            return '0.00';
        }

        $lastAccrual = $loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : $loan->loan_start_date->copy()->subDay();

        if ($settlementDate->lte($lastAccrual)) {
            return '0.00';
        }

        $cursor = $lastAccrual->copy()->addDay();
        $total = '0';

        while ($cursor->lte($settlementDate)) {
            if ($cursor->gte($loan->loan_start_date) && $cursor->lte($loan->loan_end_date)) {
                $exists = $loan->accruals()->whereDate('accrual_date', $cursor)->exists();
                if (! $exists) {
                    $dailyInterest = $this->bcMul(
                        (string) $loan->principal_amount,
                        (string) $loan->daily_rate
                    );
                    $total = $this->bcAdd($total, $dailyInterest);
                }
            }
            $cursor->addDay();
        }

        return $this->formatMoney($total);
    }

    private function accrueInterestThroughDate(Loan $loan, Carbon $settlementDate): void
    {
        $cursor = ($loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : $loan->loan_start_date->copy()->subDay())->addDay();

        while ($cursor->lte($settlementDate)) {
            if ($cursor->gte($loan->loan_start_date) && $cursor->lte($loan->loan_end_date)) {
                $loan->accrueInterestForDate($cursor);
                $loan->refresh();
            }
            $cursor->addDay();
        }
    }

    private function applyUpfrontRebateAdjustment(Loan $loan, Carbon $settlementDate): void
    {
        $earned = $this->calculateUpfrontEarnedInterest($loan, $settlementDate);
        $rebate = $this->calculateUnearnedInterestRebate($loan, $settlementDate);
        $paid = $this->sumPaidComponents($loan);
        $remaining = $this->calculateRemainingComponents($loan, $settlementDate, $paid);

        $newOutstanding = $this->bcAdd(
            $this->bcAdd($remaining['principal_remaining'], $remaining['processing_fee_remaining']),
            $remaining['interest_remaining_earned']
        );

        $loan->update([
            'interest_accrued' => (float) $earned,
            'outstanding_balance' => (float) $this->formatMoney($this->bcMax($newOutstanding, '0')),
            'rebate_amount' => (float) $rebate,
        ]);
    }

    private function closeRemainingSchedules(Loan $loan, Carbon $settlementDate): void
    {
        $loan->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->orderBy('period_number')
            ->each(function (LoanPaymentSchedule $schedule) use ($settlementDate): void {
                $schedule->update([
                    'amount_paid' => $schedule->expected_amount,
                    'remaining_amount' => 0,
                    'status' => 'paid',
                    'paid_at' => $settlementDate,
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $quote
     */
    private function resolveRepaymentRecord(
        Loan $loan,
        array $payload,
        string $appliedAmount,
        Carbon $settlementDate,
        array $quote,
    ): Repayment {
        if (! empty($payload['repayment_id'])) {
            $repayment = Repayment::query()->findOrFail((int) $payload['repayment_id']);

            if ($repayment->customer_id !== $loan->customer_id) {
                throw new InvalidArgumentException('Repayment does not belong to this loan customer.');
            }

            return $repayment;
        }

        $channelId = $payload['channel_id'] ?? $loan->channel_id;
        if (! $channelId) {
            throw new InvalidArgumentException('channel_id is required to record settlement payment.');
        }

        return Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => $channelId,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => (float) $appliedAmount,
            'phone_number' => $payload['phone_number'] ?? $loan->disbursement_phone_number ?? $loan->customer?->phone,
            'status' => 'completed',
            'processed_at' => $settlementDate,
            'metadata' => [
                'settlement' => true,
                'loan_id' => $loan->id,
                'quoted_payoff' => $quote['payoff_amount'],
                'amount_received' => $payload['amount'] ?? $appliedAmount,
            ],
        ]);
    }

    private function resolveTermDays(Loan $loan): int
    {
        $fromMeta = data_get($loan->metadata, 'term_days');
        if ($fromMeta !== null && (int) $fromMeta > 0) {
            return (int) $fromMeta;
        }

        if ($loan->loan_start_date && $loan->loan_end_date) {
            return max(1, $loan->loan_start_date->diffInDays($loan->loan_end_date));
        }

        return max(1, (int) $loan->tenure_months * 30);
    }

    /**
     * @return list<string>
     */
    private function buildQuoteNotes(
        Loan $loan,
        string $behavior,
        Carbon $settlementDate,
        string $earnedInterest,
        string $rebate,
    ): array {
        $notes = [];

        if ($behavior === Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            $notes[] = 'Upfront flat loan: payoff uses earned interest only; unearned interest is rebated.';
            if ($this->bcComp($rebate, '0') > 0) {
                $notes[] = "Unearned interest rebate at settlement: {$rebate}.";
            }
        } elseif ($behavior === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            $notes[] = 'Daily accrual loan: payoff uses interest earned to '.$settlementDate->toDateString().' only.';
            $notes[] = 'Projected schedule totals are for disclosure and are not charged on settlement.';
        } else {
            $notes[] = 'Legacy loan: payoff equals current outstanding balance (no proration).';
        }

        $notes[] = 'Processing fee is non-refundable and included if unpaid.';

        if ($loan->scheduleUsesProjectedInterest()) {
            $notes[] = 'Schedule rows marked with projected interest remain for audit; settlement uses earned amounts only.';
        }

        return $notes;
    }

    private function normalizeMoney(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("{$field} is required.");
        }

        $normalized = is_string($value)
            ? str_replace([',', ' '], '', $value)
            : (string) $value;

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        if ($this->bcComp($normalized, '0') < 0) {
            throw new InvalidArgumentException("{$field} must be zero or greater.");
        }

        return $this->formatMoney($normalized);
    }

    private function formatMoney(string $value): string
    {
        if (! function_exists('bcadd')) {
            return number_format(round((float) $value, self::MONEY_SCALE), self::MONEY_SCALE, '.', '');
        }

        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-+');

        if ($this->bcComp($absolute, '0') === 0) {
            return '0.00';
        }

        $increment = $this->bcComp($absolute, '0') > 0 ? '0.005' : '-0.005';
        $rounded = bcadd($absolute, $increment, self::MONEY_SCALE);

        return ($negative ? '-' : '').$rounded;
    }

    private function bcAdd(string $left, string $right): string
    {
        return function_exists('bcadd')
            ? bcadd($left, $right, self::CALC_SCALE)
            : (string) ((float) $left + (float) $right);
    }

    private function bcSub(string $left, string $right): string
    {
        return function_exists('bcsub')
            ? bcsub($left, $right, self::CALC_SCALE)
            : (string) ((float) $left - (float) $right);
    }

    private function bcMul(string $left, string $right): string
    {
        return function_exists('bcmul')
            ? bcmul($left, $right, self::CALC_SCALE)
            : (string) ((float) $left * (float) $right);
    }


    private function bcDiv(string $left, string $right): string
    {
        if ($this->bcComp($right, '0') === 0) {
            return '0';
        }

        return function_exists('bcdiv')
            ? bcdiv($left, $right, self::CALC_SCALE)
            : (string) ((float) $left / (float) $right);
    }

    private function bcComp(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, self::CALC_SCALE);
        }

        return ((float) $left) <=> ((float) $right);
    }

    private function bcMax(string $left, string $right): string
    {
        return $this->bcComp($left, $right) >= 0 ? $left : $right;
    }

    private function bcMin(string $left, string $right): string
    {
        return $this->bcComp($left, $right) <= 0 ? $left : $right;
    }
}
