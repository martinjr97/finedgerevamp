<?php

namespace App\Migration\Phases\Support;

use App\Migration\LegacyLoanBalanceCalculator;
use App\Models\Loan;
use App\Models\LoanProduct;

class MigratedLoanAccrualAttributes
{
    /**
     * Freeze accrual settings on migrated loans from legacy flags + target product.
     *
     * @param  array<string, mixed>  $legacyLoan
     * @return array{accrual_type: string, interest_behavior: string}
     */
    public static function resolve(array $legacyLoan, LoanProduct $loanProduct): array
    {
        $calculator = app(LegacyLoanBalanceCalculator::class);
        $isDaily = $calculator->isAccrualLoan($legacyLoan)
            || ($loanProduct->accrual_type ?? 'at_beginning') === 'daily';

        if ($isDaily) {
            return [
                'accrual_type' => 'daily',
                'interest_behavior' => Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            ];
        }

        return [
            'accrual_type' => 'at_beginning',
            'interest_behavior' => Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT,
        ];
    }
}
