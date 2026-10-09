<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Admin;
use App\Models\EmployeeLoan;
use Illuminate\Support\Facades\DB;

class EmployeeLoanSettlementService
{
    public function __construct(
        private readonly EmployeeLoanLedgerService $ledgerService,
        private readonly EmployeeLoanRepaymentService $repaymentService,
    ) {}

    /**
     * @return array<string, float>
     */
    public function quote(EmployeeLoan $loan): array
    {
        $netPaid = $this->ledgerService->calculateNetPaid($loan);
        $total = $this->ledgerService->getExpectedSettlementAmount($loan);
        $outstanding = $this->ledgerService->calculateOutstandingBalance($loan, $netPaid);
        $arrears = $this->ledgerService->outstandingArrearsInterest($loan);

        $paidPrincipal = (float) $loan->repayments()->where('status', 'completed')->sum('principal_amount');
        $paidInterest = (float) $loan->repayments()->where('status', 'completed')->sum('interest_amount');
        $paidFees = (float) $loan->repayments()->where('status', 'completed')->sum('processing_fee_amount');

        return [
            'principal_outstanding' => round(max(0, (float) $loan->principal_amount - $paidPrincipal), 2),
            'interest_outstanding' => round(max(0, (float) $loan->interest_accrued - $paidInterest), 2),
            'fees_outstanding' => round(max(0, (float) $loan->processing_fee - $paidFees), 2),
            'arrears_interest_outstanding' => $arrears,
            'settlement_total' => $outstanding,
            'contractual_total' => $total,
            'amount_paid' => $netPaid,
        ];
    }

    /**
     * @param  array<string, mixed>  $repaymentInput
     */
    public function settle(EmployeeLoan $loan, array $repaymentInput, Admin $processor): EmployeeLoan
    {
        $quote = $this->quote($loan);
        $amount = round((float) ($repaymentInput['amount'] ?? $quote['settlement_total']), 2);

        return DB::transaction(function () use ($loan, $repaymentInput, $processor, $amount, $quote) {
            if ($amount > 0) {
                $this->repaymentService->recordRepayment($loan, array_merge($repaymentInput, [
                    'amount' => $amount,
                    'notes' => trim(($repaymentInput['notes'] ?? '').' Settlement payoff'),
                ]), $processor);
            }

            $loan = $loan->fresh();
            $loan->update([
                'status' => EmployeeLoan::STATUS_SETTLED,
                'settlement_amount' => $quote['settlement_total'],
                'settlement_date' => now()->toDateString(),
                'loan_settled_date' => now()->toDateString(),
                'outstanding_balance' => 0,
            ]);

            return $loan->fresh();
        });
    }
}
