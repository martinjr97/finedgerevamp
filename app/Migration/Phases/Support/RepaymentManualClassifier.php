<?php

namespace App\Migration\Phases\Support;

use App\Migration\RepaymentAttributionService;

class RepaymentManualClassifier
{
    public const D1_HISTORICAL_SUPPORT_ONLY = 'D1_HISTORICAL_SUPPORT_ONLY';

    public const D2_CURRENT_BALANCE_BRIDGED = 'D2_CURRENT_BALANCE_BRIDGED';

    public const D3_REQUIRES_REVIEW = 'D3_REQUIRES_REVIEW';

    public const D4_BLOCKING = 'D4_BLOCKING';

    /**
     * @param  array<string, mixed>  $repayment
     */
    public function subclassify(string $attributionClass, array $repayment, ?string $exception = null): ?string
    {
        if ($attributionClass !== RepaymentAttributionService::D_MANUAL) {
            return null;
        }

        $reason = $exception ?? '';

        if (str_contains($reason, 'no_eligible') || str_contains($reason, 'no_eligible_character')) {
            return self::D1_HISTORICAL_SUPPORT_ONLY;
        }

        if (str_contains($reason, 'invalid') || str_contains($reason, 'remainder')) {
            return self::D3_REQUIRES_REVIEW;
        }

        return self::D2_CURRENT_BALANCE_BRIDGED;
    }

    public function isPromotableSubclass(?string $subclass): bool
    {
        return ! in_array($subclass, [self::D3_REQUIRES_REVIEW, self::D4_BLOCKING], true);
    }
}
