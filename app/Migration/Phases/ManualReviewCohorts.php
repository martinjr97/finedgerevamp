<?php

namespace App\Migration\Phases;

class ManualReviewCohorts
{
    /** @var list<int> */
    public const MANUAL_REVIEW_LOAN_IDS = [16969, 17617];

    /** @var list<int> */
    public const MANUAL_REVIEW_USER_IDS = [1835];

    public static function loanCohort(int $legacyLoanId, string $reconciliationStatus): string
    {
        if (in_array($legacyLoanId, self::MANUAL_REVIEW_LOAN_IDS, true)) {
            return 'COHORT_C_MANUAL_REVIEW';
        }

        return match ($reconciliationStatus) {
            'PASS' => 'COHORT_A_AUTO_PROMOTE',
            'PASS_WITH_MIGRATION_ADJUSTMENT' => 'COHORT_B_OPENING_POSITION_PROMOTE',
            'MANUAL_REVIEW' => 'COHORT_C_MANUAL_REVIEW',
            'FAIL' => 'COHORT_D_BLOCKED',
            default => 'COHORT_D_BLOCKED',
        };
    }

    public static function isPromotable(string $cohort): bool
    {
        return in_array($cohort, ['COHORT_A_AUTO_PROMOTE', 'COHORT_B_OPENING_POSITION_PROMOTE'], true);
    }
}
