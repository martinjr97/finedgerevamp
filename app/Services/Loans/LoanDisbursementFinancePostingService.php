<?php

namespace App\Services\Loans;

use App\Models\Bank;
use App\Models\Loan;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class LoanDisbursementFinancePostingService
{
    /**
     * Debit the treasury account that funded a loan disbursement.
     *
     * Idempotent: skips if finance was already posted for this loan disbursement.
     */
    public function debitSourceAccount(
        Loan $loan,
        string $sourceType,
        int $sourceId,
        ?float $amount = null
    ): void {
        $loan->refresh();

        if ($this->isAlreadyPosted($loan)) {
            return;
        }

        $amount = $amount ?? (float) $loan->principal_amount;

        if ($amount <= 0) {
            return;
        }

        match ($sourceType) {
            'bank' => Bank::find($sourceId)?->updateBalance($amount, 'debit'),
            'wallet' => Wallet::find($sourceId)?->updateBalance($amount, 'debit'),
            default => Log::warning('Unknown disbursed_via_type for finance posting', [
                'loan_id' => $loan->id,
                'disbursed_via_type' => $sourceType,
            ]),
        };

        $metadata = $loan->metadata ?? [];
        $loan->update([
            'disbursed_via_type' => $sourceType,
            'disbursed_via_id' => $sourceId,
            'metadata' => array_merge($metadata, [
                'finance_disbursement_posted_at' => now()->toIso8601String(),
                'finance_disbursement_posted_amount' => round($amount, 2),
            ]),
        ]);
    }

    public function isAlreadyPosted(Loan $loan): bool
    {
        $metadata = $loan->metadata ?? [];

        return isset($metadata['finance_disbursement_posted_at']);
    }
}
