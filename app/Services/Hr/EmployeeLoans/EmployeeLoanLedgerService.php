<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanArrearsAccrual;
use App\Models\EmployeeLoanRepayment;
use Carbon\Carbon;

class EmployeeLoanLedgerService
{
    public function outstandingArrearsInterest(EmployeeLoan $loan): float
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

    public function calculateNetPaid(EmployeeLoan $loan): float
    {
        return round((float) $loan->repayments()
            ->where('status', 'completed')
            ->sum('amount'), 2);
    }

    public function getContractualSettlementAmount(EmployeeLoan $loan): float
    {
        return round((float) $loan->total_amount + $this->outstandingArrearsInterest($loan), 2);
    }

    public function getExpectedSettlementAmount(EmployeeLoan $loan): float
    {
        return $this->getContractualSettlementAmount($loan);
    }

    public function calculateOutstandingBalance(EmployeeLoan $loan, ?float $netPaid = null): float
    {
        if (in_array($loan->status, [EmployeeLoan::STATUS_SETTLED], true)) {
            return 0.0;
        }

        if (! in_array($loan->status, [EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_APPROVED], true)) {
            return round(max(0, (float) $loan->total_amount - ($netPaid ?? $this->calculateNetPaid($loan))), 2);
        }

        try {
            $quote = app(EmployeeLoanSettlementQuoteCalculator::class)->quote($loan, Carbon::today());

            return (float) $quote['payoff_amount'];
        } catch (\Throwable) {
            $netPaid ??= $this->calculateNetPaid($loan);

            return round(max(0, (float) $loan->total_amount - $netPaid), 2);
        }
    }

    public function syncLoanLedger(EmployeeLoan $loan): EmployeeLoan
    {
        $netPaid = $this->calculateNetPaid($loan);
        $outstanding = $this->calculateOutstandingBalance($loan, $netPaid);
        $expected = $this->getExpectedSettlementAmount($loan);
        $isFullyPaid = $netPaid >= ($expected - 0.01) || $outstanding <= 0.01;

        $updates = [
            'amount_paid' => $netPaid,
            'outstanding_balance' => $outstanding,
        ];

        if ($isFullyPaid && in_array($loan->status, [EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_APPROVED], true)) {
            $updates['status'] = EmployeeLoan::STATUS_SETTLED;
            $updates['loan_settled_date'] = $loan->loan_settled_date ?? now()->toDateString();
        }

        $loan->update($updates);

        return $loan->fresh();
    }

    /**
     * @return array{principal_amount: float, interest_amount: float, processing_fee_amount: float, arrears_interest_amount: float}
     */
    public function calculateRepaymentAllocation(EmployeeLoan $loan, float $paymentAmount): array
    {
        if ($paymentAmount <= 0) {
            return [
                'principal_amount' => 0,
                'interest_amount' => 0,
                'processing_fee_amount' => 0,
                'arrears_interest_amount' => 0,
            ];
        }

        $unpaidArrears = $this->outstandingArrearsInterest($loan);
        $unpaidFee = max(0, (float) $loan->processing_fee - (float) $loan->repayments()->where('status', 'completed')->sum('processing_fee_amount'));
        $unpaidInterest = max(0, (float) $loan->interest_accrued - (float) $loan->repayments()->where('status', 'completed')->sum('interest_amount'));
        $unpaidPrincipal = max(0, (float) $loan->principal_amount - (float) $loan->repayments()->where('status', 'completed')->sum('principal_amount'));

        $remaining = $paymentAmount;
        $fee = min($unpaidFee, $remaining);
        $remaining -= $fee;

        $arrears = min($unpaidArrears, $remaining);
        $remaining -= $arrears;

        $interest = min($unpaidInterest, $remaining);
        $remaining -= $interest;

        $principal = min($unpaidPrincipal, $remaining);
        $remaining -= $principal;

        if ($remaining > 0.009) {
            $principal += $remaining;
        }

        return [
            'processing_fee_amount' => round($fee, 2),
            'arrears_interest_amount' => round($arrears, 2),
            'interest_amount' => round($interest, 2),
            'principal_amount' => round($principal, 2),
        ];
    }

    public static function scheduleAppliedAmountFromAllocation(array $allocation): float
    {
        return round(max(0,
            (float) ($allocation['principal_amount'] ?? 0)
            + (float) ($allocation['interest_amount'] ?? 0)
            + (float) ($allocation['processing_fee_amount'] ?? 0)
        ), 2);
    }
}
