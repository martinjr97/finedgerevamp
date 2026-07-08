<?php

namespace App\PaymentPlatform\Support;

class CGrateUatDisbursementIssuer
{
    public static function isForced(): bool
    {
        return (bool) config('cgrate.uat.force_disbursement_issuer_name', false);
    }

    public static function forcedIssuerName(): string
    {
        return (string) config('cgrate.uat.disbursement_issuer_name', '543');
    }

    /**
     * When enabled, all cGrate disbursements use the configured UAT issuer (default 543).
     */
    public static function applyToIssuerName(string $issuerName, string $gatewayCode): string
    {
        if ($gatewayCode !== 'cgrate' || ! self::isForced()) {
            return $issuerName;
        }

        return self::forcedIssuerName();
    }
}
