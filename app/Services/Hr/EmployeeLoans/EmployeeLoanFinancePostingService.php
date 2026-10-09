<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Bank;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanRepayment;
use App\Models\FinancialTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeLoanFinancePostingService
{
    public function postDisbursement(EmployeeLoan $loan, string $sourceType, int $sourceId, ?float $amount = null): void
    {
        $loan->refresh();
        $metadata = $loan->metadata ?? [];

        if (isset($metadata['finance_disbursement_posted_at'])) {
            return;
        }

        $amount = $amount ?? (float) $loan->principal_amount;
        if ($amount <= 0) {
            return;
        }

        match ($sourceType) {
            'bank' => Bank::find($sourceId)?->updateBalance($amount, 'debit'),
            'wallet' => Wallet::find($sourceId)?->updateBalance($amount, 'debit'),
            default => Log::warning('Unknown employee loan disbursement source', [
                'employee_loan_id' => $loan->id,
                'source_type' => $sourceType,
            ]),
        };

        FinancialTransaction::create([
            'transaction_number' => 'EL-DIS-'.Str::upper(Str::random(10)),
            'transaction_date' => now()->toDateString(),
            'type' => 'expense',
            'category' => 'employee_loan_disbursement',
            'description' => "Employee loan disbursement {$loan->loan_number}",
            'amount' => round($amount, 2),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_number' => $loan->loan_number,
            'metadata' => [
                'employee_loan_id' => $loan->id,
                'employee_id' => $loan->employee_id,
            ],
            'created_by' => auth('admin')->id(),
            'approval_status' => 'approved',
        ]);

        $loan->update([
            'disbursed_via_type' => $sourceType,
            'disbursed_via_id' => $sourceId,
            'metadata' => array_merge($metadata, [
                'finance_disbursement_posted_at' => now()->toIso8601String(),
                'finance_disbursement_posted_amount' => round($amount, 2),
            ]),
        ]);
    }

    public function postRepayment(EmployeeLoanRepayment $repayment, string $destinationType, int $destinationId): void
    {
        $repayment->loadMissing('employeeLoan');
        $loan = $repayment->employeeLoan;
        $metadata = $repayment->metadata ?? [];

        if (isset($metadata['finance_repayment_posted_at'])) {
            return;
        }

        $amount = (float) $repayment->amount;
        if ($amount <= 0) {
            return;
        }

        match ($destinationType) {
            'bank' => Bank::find($destinationId)?->updateBalance($amount, 'credit'),
            'wallet' => Wallet::find($destinationId)?->updateBalance($amount, 'credit'),
            default => Log::warning('Unknown employee loan repayment destination', [
                'employee_loan_repayment_id' => $repayment->id,
                'destination_type' => $destinationType,
            ]),
        };

        $repayment->update([
            'metadata' => array_merge($metadata, [
                'finance_repayment_posted_at' => now()->toIso8601String(),
                'received_via_type' => $destinationType,
                'received_via_id' => $destinationId,
            ]),
        ]);

        $this->postRepaymentIncomeTransactions($repayment, $loan);
    }

    protected function postRepaymentIncomeTransactions(EmployeeLoanRepayment $repayment, EmployeeLoan $loan): void
    {
        $components = [
            'employee_loan_interest' => (float) $repayment->interest_amount,
            'employee_loan_processing_fee' => (float) $repayment->processing_fee_amount,
            'employee_loan_arrears_interest' => (float) $repayment->arrears_interest_amount,
        ];

        foreach ($components as $category => $componentAmount) {
            if ($componentAmount <= 0) {
                continue;
            }

            FinancialTransaction::create([
                'transaction_number' => 'EL-INC-'.Str::upper(Str::random(8)),
                'transaction_date' => $repayment->effective_date?->toDateString() ?? now()->toDateString(),
                'type' => 'income',
                'category' => $category,
                'description' => "Employee loan repayment {$loan->loan_number} ({$category})",
                'amount' => round($componentAmount, 2),
                'reference_number' => $repayment->reference,
                'metadata' => [
                    'employee_loan_id' => $loan->id,
                    'employee_loan_repayment_id' => $repayment->id,
                ],
                'created_by' => auth('admin')->id(),
                'approval_status' => 'approved',
            ]);
        }
    }
}
