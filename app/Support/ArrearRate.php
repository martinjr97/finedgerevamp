<?php

namespace App\Support;

/**
 * Arrear rate semantics match loan_rates.arrear_rate and daily_rate storage:
 * the stored decimal is the daily factor (e.g. 0.01 means 1% per day, charge = base × 0.01).
 *
 * UI displays this as (value × 100)% via formatRateMultiplier in loan applications.
 */
final class ArrearRate
{
    public static function dailyFactor(?string $arrearRate): string
    {
        if ($arrearRate === null || $arrearRate === '') {
            return '0';
        }

        $normalized = trim($arrearRate);
        if ((float) $normalized <= 0) {
            return '0';
        }

        return $normalized;
    }

    public static function calculateDailyCharge(string $openingOverdueAmount, string $dailyFactor): string
    {
        $opening = (float) $openingOverdueAmount;
        $factor = (float) $dailyFactor;

        if ($opening <= 0 || $factor <= 0) {
            return '0.00';
        }

        return self::roundMoney((string) ($opening * $factor));
    }

    public static function roundMoney(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function displayPercent(?string $dailyFactor): ?string
    {
        if ($dailyFactor === null || (float) $dailyFactor <= 0) {
            return null;
        }

        return number_format((float) $dailyFactor * 100, 4, '.', '').'%';
    }

    /**
     * Convert admin-entered daily percentage (e.g. 1 = 1% per day) to stored daily factor (0.01).
     */
    public static function fromPercentage(float|string $percent): string
    {
        $value = (float) $percent;

        return number_format($value / 100, 5, '.', '');
    }

    /**
     * Convert stored daily factor to admin display value (e.g. 0.01 → 1.00).
     */
    public static function toPercentage(?string $dailyFactor): string
    {
        if ($dailyFactor === null || $dailyFactor === '') {
            return '0';
        }

        return number_format((float) $dailyFactor * 100, 2, '.', '');
    }
}
