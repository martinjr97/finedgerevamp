<?php

namespace App\Support;

use Carbon\Carbon;

final class PmecDateFormatter
{
    /**
     * PMEC BEGDA: first calendar day of the loan start month.
     */
    public static function begdaFromLoanStart(?Carbon $loanStartDate): ?Carbon
    {
        if (! $loanStartDate) {
            return null;
        }

        return $loanStartDate->copy()->startOfMonth();
    }

    /**
     * PMEC ENDDA: last calendar day of the loan end month.
     */
    public static function enddaFromLoanEnd(?Carbon $loanEndDate): ?Carbon
    {
        if (! $loanEndDate) {
            return null;
        }

        return $loanEndDate->copy()->endOfMonth();
    }

    public static function format(Carbon $date): string
    {
        return $date->format('d.m.Y');
    }
}
