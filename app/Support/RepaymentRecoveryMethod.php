<?php

namespace App\Support;

final class RepaymentRecoveryMethod
{
    public const NORMAL = 'normal';

    public const LITIGATION = 'litigation';

    public const PAYROLL_DEDUCTION = 'payroll_deduction';

    public const MOBILE_MONEY = 'mobile_money';

    public const MANUAL_COLLECTION = 'manual_collection';

    public const COLLATERAL_RECOVERY = 'collateral_recovery';

    public const SETTLEMENT_AGREEMENT = 'settlement_agreement';

    public const OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::NORMAL => 'Normal',
            self::LITIGATION => 'Litigation',
            self::PAYROLL_DEDUCTION => 'Payroll Deduction',
            self::MOBILE_MONEY => 'Mobile Money',
            self::MANUAL_COLLECTION => 'Manual Collection',
            self::COLLATERAL_RECOVERY => 'Collateral Recovery',
            self::SETTLEMENT_AGREEMENT => 'Settlement Agreement',
            self::OTHER => 'Other',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::labels());
    }

    public static function label(?string $method): string
    {
        if ($method === null || $method === '') {
            return self::labels()[self::NORMAL];
        }

        return self::labels()[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    public static function isValid(?string $method): bool
    {
        return $method !== null && in_array($method, self::values(), true);
    }
}
