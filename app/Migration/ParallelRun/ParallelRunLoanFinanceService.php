<?php

namespace App\Migration\ParallelRun;

use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Services\Loans\LoanDisbursementFinancePostingService;

class ParallelRunLoanFinanceService
{
    public function __construct(
        private readonly ParallelRunExpenseImportService $expenseImport,
        private readonly ParallelRunTreasuryResolver $treasury,
        private readonly LoanDisbursementFinancePostingService $disbursementPosting,
    ) {}

    /**
     * Mirror legacy loan disbursement treasury movement in revamp.
     *
     * @return array<string, mixed>
     */
    public function postDisbursementFromLegacy(Loan $loan, int $legacyLoanId): array
    {
        if (! config('legacy-parallel-run.finance_on_import_enabled', false)) {
            return ['status' => 'disabled'];
        }

        $loan->refresh();

        if ($this->disbursementPosting->isAlreadyPosted($loan)) {
            return ['status' => 'already_posted'];
        }

        $expenseResult = $this->expenseImport->importLoanDisbursementExpense(
            $legacyLoanId,
            (float) $loan->principal_amount,
        );

        if (in_array($expenseResult['status'], ['created', 'matched'], true) && isset($expenseResult['transaction_id'])) {
            $transaction = FinancialTransaction::query()->find($expenseResult['transaction_id']);
            if ($transaction && $transaction->source_type && $transaction->source_id) {
                $loan->update([
                    'disbursed_via_type' => $transaction->source_type,
                    'disbursed_via_id' => $transaction->source_id,
                    'metadata' => array_merge($loan->metadata ?? [], [
                        'finance_disbursement_posted_at' => now()->toIso8601String(),
                        'finance_disbursement_posted_amount' => round((float) $loan->principal_amount, 2),
                        'finance_disbursement_source' => 'legacy_expense',
                        'legacy_disbursement_expense_id' => $transaction->metadata['legacy_id'] ?? null,
                    ]),
                ]);

                return array_merge($expenseResult, ['loan_finance' => 'linked_expense']);
            }
        }

        if (($expenseResult['status'] ?? '') === 'fallback_wallet') {
            $walletId = (int) ($expenseResult['wallet_id'] ?? 0);
            if ($walletId > 0) {
                $this->disbursementPosting->debitSourceAccount($loan, 'wallet', $walletId);

                return array_merge($expenseResult, ['loan_finance' => 'default_wallet_debit']);
            }
        }

        return array_merge($expenseResult, ['loan_finance' => 'not_posted']);
    }
}
