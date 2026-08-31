<?php

namespace App\Services;

use App\Models\Creditor;
use App\Models\ExpenseCategory;

class CreditorBalanceService
{
    public const CREDITOR_LOAN_REPAYMENT_CODE = 'creditor_loan_repayment';

    public const CREDITOR_LOAN_REPAYMENT_NAME = 'Creditor Loan Repayment';

    public function isCreditorLoanRepayment(?ExpenseCategory $category): bool
    {
        if (! $category) {
            return false;
        }

        if ($category->code === self::CREDITOR_LOAN_REPAYMENT_CODE) {
            return true;
        }

        return strcasecmp(trim($category->name), self::CREDITOR_LOAN_REPAYMENT_NAME) === 0;
    }

    public function reduceBalance(Creditor $creditor, float $amount): void
    {
        $creditor->update([
            'amount' => max(0, round((float) $creditor->amount - $amount, 2)),
        ]);
    }

    public function restoreBalance(Creditor $creditor, float $amount): void
    {
        $creditor->update([
            'amount' => round((float) $creditor->amount + $amount, 2),
        ]);
    }

    public function paymentExceedsBalance(Creditor $creditor, float $amount): bool
    {
        return $amount > (float) $creditor->amount + 0.00001;
    }
}
