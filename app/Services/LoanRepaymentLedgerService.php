<?php

namespace App\Services;

use App\Models\Loan;

class LoanRepaymentLedgerService
{
    /**
     * Net paid = sum of all loan repayment amounts (positive payments + negative refunds).
     */
    public function getExpectedSettlementAmount(Loan $loan): float
    {
        if ($loan->paymentSchedules()->exists()) {
            return round((float) $loan->getScheduleExpectedTotal(), 2);
        }

        return round((float) $loan->total_amount, 2);
    }

    public function calculateNetPaid(Loan $loan): float
    {
        return round((float) $loan->loanRepayments()->sum('amount'), 2);
    }

    public function calculateSuspenseAmount(Loan $loan, ?float $netPaid = null): float
    {
        $netPaid ??= $this->calculateNetPaid($loan);
        $expected = $this->getExpectedSettlementAmount($loan);

        return round(max(0, $netPaid - $expected), 2);
    }

    public function calculateOutstandingBalance(Loan $loan, ?float $netPaid = null): float
    {
        $netPaid ??= $this->calculateNetPaid($loan);
        $expected = $this->getExpectedSettlementAmount($loan);

        return round(max(0, $expected - $netPaid), 2);
    }

    /**
     * Schedule reversal only applies after suspense (amount above full obligation) is exhausted.
     */
    public function scheduleReversalAmountForRefund(Loan $loan, float $refundAmount, ?float $netPaidBeforeRefund = null): float
    {
        $netPaidBeforeRefund ??= $this->calculateNetPaid($loan);
        $suspenseBefore = $this->calculateSuspenseAmount($loan, $netPaidBeforeRefund);

        return round(max(0, $refundAmount - $suspenseBefore), 2);
    }

    public function syncLoanLedger(Loan $loan): Loan
    {
        $netPaid = $this->calculateNetPaid($loan);
        $expected = $this->getExpectedSettlementAmount($loan);
        $outstanding = $this->calculateOutstandingBalance($loan, $netPaid);
        $isFullyPaid = $netPaid >= ($expected - 0.01);

        $updates = [
            'amount_paid' => $netPaid,
            'outstanding_balance' => $outstanding,
        ];

        if ($isFullyPaid) {
            if (in_array($loan->status, ['approved', 'active'], true)) {
                $updates['status'] = 'settled';
            }

            if (! $loan->loan_settled_date) {
                $updates['loan_settled_date'] = now()->toDateString();
            }
        } elseif (in_array($loan->status, ['settled', 'completed'], true)) {
            $updates['status'] = 'active';
            $updates['loan_settled_date'] = null;
            $updates['settlement_date'] = null;
            $updates['settlement_amount'] = null;
            $updates['rebate_amount'] = null;
        }

        $loan->update($updates);

        return $loan->fresh();
    }
}
