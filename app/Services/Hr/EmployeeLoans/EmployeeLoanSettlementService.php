<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Admin;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeLoanSettlementService
{
    public function __construct(
        private readonly EmployeeLoanRepaymentService $repaymentService,
        private readonly EmployeeLoanSettlementQuoteCalculator $quoteCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quote(EmployeeLoan $loan, Carbon|string|null $settlementDate = null): array
    {
        $date = $settlementDate === null ? Carbon::today() : ($settlementDate instanceof Carbon ? $settlementDate : Carbon::parse($settlementDate));

        return $this->quoteCalculator->quote($loan, $date);
    }

    /**
     * @param  array<string, mixed>  $repaymentInput
     */
    public function settle(EmployeeLoan $loan, array $repaymentInput, Admin $processor): EmployeeLoan
    {
        $settlementDate = Carbon::parse($repaymentInput['effective_date'] ?? now()->toDateString())->startOfDay();

        return DB::transaction(function () use ($loan, $repaymentInput, $processor, $settlementDate) {
            $loan = EmployeeLoan::query()->lockForUpdate()->findOrFail($loan->id);

            $loan = $this->quoteCalculator->prepareLoanForSettlementQuote($loan, $settlementDate);
            $quote = $this->quoteCalculator->quote($loan, $settlementDate);
            $payoff = (float) $quote['payoff_amount'];

            if ($payoff <= 0) {
                throw new \InvalidArgumentException('This loan has no outstanding balance to settle.');
            }

            $this->repaymentService->recordRepayment($loan, array_merge($repaymentInput, [
                'amount' => $payoff,
                'effective_date' => $settlementDate->toDateString(),
                'notes' => trim(($repaymentInput['notes'] ?? '').' Settlement payoff'),
                'metadata' => array_merge($repaymentInput['metadata'] ?? [], [
                    'settlement' => true,
                    'quoted_payoff' => $payoff,
                    'interest_earned' => $quote['interest_earned'],
                    'unearned_interest_rebate' => $quote['unearned_interest_rebate'],
                ]),
            ]), $processor);

            $this->closeRemainingSchedules($loan->fresh(), $settlementDate);

            $loan->fresh()->update([
                'status' => EmployeeLoan::STATUS_SETTLED,
                'settlement_amount' => $payoff,
                'settlement_date' => $settlementDate->toDateString(),
                'loan_settled_date' => $settlementDate->toDateString(),
                'outstanding_balance' => 0,
                'metadata' => array_merge($loan->metadata ?? [], [
                    'settlement' => [
                        'settlement_date' => $settlementDate->toDateString(),
                        'payoff_amount' => $payoff,
                        'rebate_amount' => $quote['unearned_interest_rebate'],
                        'interest_earned' => $quote['interest_earned'],
                    ],
                ]),
            ]);

            return $loan->fresh();
        });
    }

    public function finalizeIfFullyPaid(EmployeeLoan $loan): ?EmployeeLoan
    {
        $loan->refresh();

        if ($loan->status === EmployeeLoan::STATUS_SETTLED) {
            return $loan;
        }

        if (! in_array($loan->status, [EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_APPROVED], true)) {
            return null;
        }

        try {
            $quote = $this->quoteCalculator->quote($loan, Carbon::today());
        } catch (\Throwable) {
            return null;
        }

        if ((float) $quote['payoff_amount'] > 0.01) {
            return null;
        }

        $loan->update([
            'status' => EmployeeLoan::STATUS_SETTLED,
            'settlement_amount' => (float) ($loan->settlement_amount ?? $quote['payoff_amount']),
            'settlement_date' => $loan->settlement_date ?? now()->toDateString(),
            'loan_settled_date' => $loan->loan_settled_date ?? now()->toDateString(),
            'outstanding_balance' => 0,
        ]);

        return $loan->fresh();
    }

    private function closeRemainingSchedules(EmployeeLoan $loan, Carbon $settlementDate): void
    {
        $loan->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->orderBy('period_number')
            ->each(function (EmployeeLoanPaymentSchedule $schedule) use ($settlementDate): void {
                $schedule->update([
                    'amount_paid' => $schedule->expected_amount,
                    'remaining_amount' => 0,
                    'status' => 'paid',
                    'paid_at' => $settlementDate,
                ]);
            });
    }
}
