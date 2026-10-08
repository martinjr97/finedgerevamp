<?php

namespace App\Services\Hr;

use Carbon\Carbon;

/**
 * Leave day calculation: inclusive calendar days between start and end.
 * Weekends and public holidays are NOT excluded (not modeled in this application).
 */
class LeaveDurationCalculator
{
    public function calculateDays(string $startDate, string $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return (float) ($start->diffInDays($end) + 1);
    }
}
