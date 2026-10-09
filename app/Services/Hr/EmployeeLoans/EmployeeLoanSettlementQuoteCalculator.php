<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanAccrual;
use App\Models\EmployeeLoanArrearsAccrual;
use App\Models\EmployeeLoanRepayment;
use App\Models\Loan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use RuntimeException;

class EmployeeLoanSettlementQuoteCalculator
{
    private const MONEY_SCALE = 2;

    private const CALC_SCALE = 12;

    public function __construct(
        private readonly EmployeeLoanInterestAccrualService $interestAccrualService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quote(EmployeeLoan $loan, Carbon|string|null $settlementDate = null): array
    {
        $this->assertLoanCanBeQuoted($loan);

        $settlementDate = $this->resolveSettlementDate($loan, $settlementDate);
        $behavior = $this->resolveSettlementBehavior($loan);

        $interestBooked = $this->getInterestBookedAmount($loan);
        $earnedInterest = $this->calculateEarnedInterest($loan, $settlementDate);
        $rebate = $this->calculateUnearnedInterestRebate($loan, $settlementDate);

        $paid = $this->sumPaidComponents($loan);
        $remaining = $this->calculateRemainingComponents($loan, $settlementDate, $paid);
        $payoff = $this->calculatePayoffAmount($loan, $settlementDate, $remaining);
        $arrearsOutstanding = $this->outstandingArrearsInterest($loan);

        return [
            'settlement_date' => $settlementDate->toDateString(),
            'principal_outstanding' => (float) $remaining['principal_remaining'],
            'interest_outstanding' => (float) $remaining['interest_remaining_earned'],
            'fees_outstanding' => (float) $remaining['processing_fee_remaining'],
            'arrears_interest_outstanding' => $arrearsOutstanding,
            'interest_booked' => (float) $interestBooked,
            'interest_earned' => (float) $earnedInterest,
            'interest_paid' => (float) $paid['interest_paid'],
            'unearned_interest_rebate' => (float) $rebate,
            'settlement_total' => (float) $payoff,
            'payoff_amount' => (float) $payoff,
            'contractual_total' => (float) $loan->total_amount,
            'amount_paid' => (float) $paid['total_paid'],
            'interest_behavior' => $behavior,
            'notes' => $this->buildQuoteNotes($loan, $behavior, $settlementDate, $earnedInterest, $rebate),
        ];
    }

    public function prepareLoanForSettlementQuote(EmployeeLoan $loan, Carbon $settlementDate): EmployeeLoan
    {
        $loan = $loan->fresh();

        if ($this->resolveSettlementBehavior($loan) === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            $this->accrueInterestThroughDate($loan, $settlementDate);
            $loan = $loan->fresh();
        }

        if ($this->resolveSettlementBehavior($loan) === Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            $this->applyUpfrontEarnedInterestSnapshot($loan, $settlementDate);
            $loan = $loan->fresh();
        }

        return $loan;
    }

    /**
     * @param  array{principal_remaining: string, processing_fee_remaining: string, interest_remaining_earned: string}  $remaining
     */
    public function calculatePayoffAmount(EmployeeLoan $loan, Carbon $settlementDate, ?array $remaining = null): string
    {
        if ($this->resolveSettlementBehavior($loan) === 'legacy') {
            return $this->formatMoney((string) max(0, (float) $loan->outstanding_balance));
        }

        $remaining ??= $this->calculateRemainingComponents(
            $loan,
            $settlementDate,
            $this->sumPaidComponents($loan),
        );

        $payoff = $this->bcAdd(
            $this->bcAdd($remaining['principal_remaining'], $remaining['processing_fee_remaining']),
            $remaining['interest_remaining_earned']
        );

        $arrears = $this->formatMoney((string) $this->outstandingArrearsInterest($loan));
        $payoff = $this->bcAdd($payoff, $arrears);

        return $this->formatMoney($this->bcMax($payoff, '0'));
    }

    private function assertLoanCanBeQuoted(EmployeeLoan $loan): void
    {
        if ($loan->status === EmployeeLoan::STATUS_SETTLED) {
            throw new RuntimeException('Loan is already settled.');
        }

        if (! in_array($loan->status, [EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_APPROVED], true)) {
            throw new RuntimeException('Loan must be active or approved to quote settlement.');
        }
    }

    private function resolveSettlementDate(EmployeeLoan $loan, Carbon|string|null $settlementDate): Carbon
    {
        $date = $settlementDate === null
            ? Carbon::today()
            : ($settlementDate instanceof CarbonInterface
                ? $settlementDate->copy()
                : Carbon::parse($settlementDate));

        $date = $date->startOfDay();
        $start = $this->loanStartDate($loan);

        if ($start && $date->lt($start)) {
            throw new InvalidArgumentException('Settlement date cannot be before loan start date.');
        }

        if ($loan->loan_end_date && $date->gt($loan->loan_end_date)) {
            throw new InvalidArgumentException('Settlement date cannot be after loan end date.');
        }

        return $date;
    }

    private function loanStartDate(EmployeeLoan $loan): ?Carbon
    {
        if ($loan->loan_start_date) {
            return $loan->loan_start_date->copy()->startOfDay();
        }

        if ($loan->disbursement_date) {
            return $loan->disbursement_date->copy()->startOfDay();
        }

        if ($loan->application_date) {
            return $loan->application_date->copy()->startOfDay();
        }

        return null;
    }

    private function resolveSettlementBehavior(EmployeeLoan $loan): string
    {
        if (in_array($loan->interest_behavior, [
            Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
        ], true)) {
            return (string) $loan->interest_behavior;
        }

        if (($loan->accrual_type ?? '') === 'daily') {
            return Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL;
        }

        if (($loan->accrual_type ?? '') === 'at_beginning') {
            return Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT;
        }

        return 'legacy';
    }

    /**
     * @return array{principal_paid: string, interest_paid: string, processing_fee_paid: string, total_paid: string}
     */
    private function sumPaidComponents(EmployeeLoan $loan): array
    {
        $query = $loan->repayments()->where('status', 'completed');

        return [
            'principal_paid' => $this->formatMoney((string) $query->sum('principal_amount')),
            'interest_paid' => $this->formatMoney((string) $loan->repayments()->where('status', 'completed')->sum('interest_amount')),
            'processing_fee_paid' => $this->formatMoney((string) $loan->repayments()->where('status', 'completed')->sum('processing_fee_amount')),
            'total_paid' => $this->formatMoney((string) $query->sum('amount')),
        ];
    }

    /**
     * @param  array{principal_paid: string, interest_paid: string, processing_fee_paid: string}  $paid
     * @return array{principal_remaining: string, processing_fee_remaining: string, interest_remaining_earned: string}
     */
    private function calculateRemainingComponents(EmployeeLoan $loan, Carbon $settlementDate, array $paid): array
    {
        $principal = $this->formatMoney((string) $loan->principal_amount);
        $fee = $this->formatMoney((string) $loan->processing_fee);
        $earned = $this->calculateEarnedInterest($loan, $settlementDate);

        return [
            'principal_remaining' => $this->formatMoney($this->bcMax($this->bcSub($principal, $paid['principal_paid']), '0')),
            'processing_fee_remaining' => $this->formatMoney($this->bcMax($this->bcSub($fee, $paid['processing_fee_paid']), '0')),
            'interest_remaining_earned' => $this->formatMoney($this->bcMax($this->bcSub($earned, $paid['interest_paid']), '0')),
        ];
    }

    private function getInterestBookedAmount(EmployeeLoan $loan): string
    {
        if ($this->resolveSettlementBehavior($loan) === Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            $fromColumns = max(
                0,
                (float) $loan->total_amount - (float) $loan->principal_amount - (float) $loan->processing_fee
            );
            if ($fromColumns > 0) {
                return $this->formatMoney((string) $fromColumns);
            }

            $fromMeta = data_get($loan->metadata, 'approved_pricing_quote.interest')
                ?? data_get($loan->metadata, 'pricing_quote.interest')
                ?? data_get($loan->metadata, 'draft_pricing.interest');

            if ($fromMeta !== null) {
                return $this->formatMoney((string) $fromMeta);
            }
        }

        return $this->formatMoney((string) $loan->interest_accrued);
    }

    private function outstandingArrearsInterest(EmployeeLoan $loan): float
    {
        $accrued = (float) EmployeeLoanArrearsAccrual::query()
            ->where('employee_loan_id', $loan->id)
            ->sum('interest_amount');

        $paid = (float) EmployeeLoanRepayment::query()
            ->where('employee_loan_id', $loan->id)
            ->where('status', 'completed')
            ->sum('arrears_interest_amount');

        return round(max(0, $accrued - $paid), 2);
    }

    public function calculateEarnedInterest(EmployeeLoan $loan, Carbon $settlementDate): string
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

    public function calculateUnearnedInterestRebate(EmployeeLoan $loan, Carbon $settlementDate): string
    {
        if ($this->resolveSettlementBehavior($loan) !== Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return '0.00';
        }

        $booked = $this->getInterestBookedAmount($loan);
        $earned = $this->calculateUpfrontEarnedInterest($loan, $settlementDate);
        $rebate = $this->bcSub($booked, $earned);

        return $this->formatMoney($this->bcMax($rebate, '0'));
    }

    private function calculateUpfrontEarnedInterest(EmployeeLoan $loan, Carbon $settlementDate): string
    {
        $booked = $this->getInterestBookedAmount($loan);
        $termDays = $this->resolveTermDays($loan);
        $start = $this->loanStartDate($loan);

        if ($this->bcComp($booked, '0') <= 0 || $termDays < 1 || ! $start) {
            return '0.00';
        }

        $elapsedDays = min(
            $termDays,
            max(1, $start->diffInDays($settlementDate) + 1)
        );

        $earned = $this->bcDiv(
            $this->bcMul($booked, (string) $elapsedDays),
            (string) $termDays
        );

        return $this->formatMoney($this->bcMin($earned, $booked));
    }

    private function calculateProjectedDailyAccrualThroughDate(EmployeeLoan $loan, Carbon $settlementDate): string
    {
        if ($this->resolveSettlementBehavior($loan) !== Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            return '0.00';
        }

        if (! $loan->daily_rate || (float) $loan->daily_rate <= 0) {
            return '0.00';
        }

        $start = $this->loanStartDate($loan);
        if (! $start) {
            return '0.00';
        }

        $lastAccrual = $loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : $start->copy()->subDay();

        if ($settlementDate->lte($lastAccrual)) {
            return '0.00';
        }

        $cursor = $lastAccrual->copy()->addDay();
        $total = '0';
        $end = $loan->loan_end_date?->copy()->startOfDay() ?? $settlementDate;

        while ($cursor->lte($settlementDate)) {
            if ($cursor->gte($start) && $cursor->lte($end)) {
                $exists = EmployeeLoanAccrual::query()
                    ->where('employee_loan_id', $loan->id)
                    ->whereDate('accrual_date', $cursor)
                    ->exists();
                if (! $exists) {
                    $principalBalance = max(0, (float) $loan->principal_amount - (float) $loan->repayments()->where('status', 'completed')->sum('principal_amount'));
                    $dailyInterest = $this->bcMul((string) $principalBalance, (string) $loan->daily_rate);
                    $total = $this->bcAdd($total, $dailyInterest);
                }
            }
            $cursor->addDay();
        }

        return $this->formatMoney($total);
    }

    private function accrueInterestThroughDate(EmployeeLoan $loan, Carbon $settlementDate): void
    {
        $start = $this->loanStartDate($loan);
        if (! $start) {
            return;
        }

        $cursor = ($loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : $start->copy()->subDay())->addDay();

        while ($cursor->lte($settlementDate)) {
            $this->interestAccrualService->accrueLoanForDate($loan->fresh(), $cursor);
            $cursor->addDay();
        }
    }

    private function applyUpfrontEarnedInterestSnapshot(EmployeeLoan $loan, Carbon $settlementDate): void
    {
        $earned = $this->calculateUpfrontEarnedInterest($loan, $settlementDate);
        $paid = $this->sumPaidComponents($loan);
        $remaining = $this->calculateRemainingComponents($loan, $settlementDate, $paid);

        $newOutstanding = $this->bcAdd(
            $this->bcAdd($remaining['principal_remaining'], $remaining['processing_fee_remaining']),
            $remaining['interest_remaining_earned']
        );

        $rebate = $this->calculateUnearnedInterestRebate($loan, $settlementDate);

        $loan->update([
            'interest_accrued' => (float) $earned,
            'outstanding_balance' => (float) $this->formatMoney($this->bcMax($newOutstanding, '0')),
            'metadata' => array_merge($loan->metadata ?? [], [
                'settlement_rebate_preview' => (float) $rebate,
            ]),
        ]);
    }

    private function resolveTermDays(EmployeeLoan $loan): int
    {
        $fromMeta = data_get($loan->metadata, 'pricing_quote.term_days')
            ?? data_get($loan->metadata, 'approved_pricing_quote.term_days');

        if ($fromMeta !== null && (int) $fromMeta > 0) {
            return (int) $fromMeta;
        }

        $start = $this->loanStartDate($loan);
        if ($start && $loan->loan_end_date) {
            return max(1, $start->diffInDays($loan->loan_end_date));
        }

        return max(1, (int) $loan->tenure_months * 30);
    }

    /**
     * @return list<string>
     */
    private function buildQuoteNotes(EmployeeLoan $loan, string $behavior, Carbon $settlementDate, string $earnedInterest, string $rebate): array
    {
        $notes = [];

        if ($behavior === Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            $notes[] = 'Upfront interest: payoff uses earned interest to '.$settlementDate->toDateString().'; unearned interest is not charged.';
            if ($this->bcComp($rebate, '0') > 0) {
                $notes[] = 'Unearned interest rebate: K '.number_format((float) $rebate, 2).'.';
            }
        } elseif ($behavior === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            $notes[] = 'Daily accrual: payoff uses interest earned through '.$settlementDate->toDateString().' only.';
        } else {
            $notes[] = 'Payoff based on current loan balances.';
        }

        $notes[] = 'Processing fee is included in payoff if still unpaid.';

        return $notes;
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
        return function_exists('bcadd') ? bcadd($left, $right, self::CALC_SCALE) : (string) ((float) $left + (float) $right);
    }

    private function bcSub(string $left, string $right): string
    {
        return function_exists('bcsub') ? bcsub($left, $right, self::CALC_SCALE) : (string) ((float) $left - (float) $right);
    }

    private function bcMul(string $left, string $right): string
    {
        return function_exists('bcmul') ? bcmul($left, $right, self::CALC_SCALE) : (string) ((float) $left * (float) $right);
    }

    private function bcDiv(string $left, string $right): string
    {
        if ($this->bcComp($right, '0') === 0) {
            return '0';
        }

        return function_exists('bcdiv') ? bcdiv($left, $right, self::CALC_SCALE) : (string) ((float) $left / (float) $right);
    }

    private function bcComp(string $left, string $right): int
    {
        return function_exists('bccomp') ? bccomp($left, $right, self::CALC_SCALE) : ((float) $left) <=> ((float) $right);
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
