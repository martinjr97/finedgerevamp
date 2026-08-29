<?php

namespace App\Migration;

/**
 * Legacy outstanding balance rules aligned with legacy admin UI and replay strategies.
 *
 * - MOU / GRZ (accrual): current_loan_amount
 * - Character / marketeer (fixed): loan_amount - repaid_amount (legacy loans/show.blade.php)
 */
class LegacyLoanBalanceCalculator
{
    /**
     * @param  array<string, mixed>  $loan
     */
    public function effectiveOutstanding(array $loan): float
    {
        if ($this->isAccrualLoan($loan)) {
            return max(0, (float) ($loan['current_loan_amount'] ?? 0));
        }

        return $this->fixedProductOutstanding($loan);
    }

    /**
     * Fixed-rate products (character, marketeer): legacy UI uses loan_amount - repaid_amount.
     *
     * @param  array<string, mixed>  $loan
     */
    public function fixedProductOutstanding(array $loan): float
    {
        $loanAmount = (float) ($loan['loan_amount'] ?? 0);
        $repaidAmount = (float) ($loan['repaid_amount'] ?? 0);

        return max(0, round($loanAmount - $repaidAmount, 2));
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    public function isAccrualLoan(array $loan): bool
    {
        return (bool) ($loan['salary_based'] ?? false) || (bool) ($loan['gvnt_loan'] ?? false);
    }
}
