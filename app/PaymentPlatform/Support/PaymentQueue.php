<?php

namespace App\PaymentPlatform\Support;

use App\Support\Queue\FinancialQueue;

/**
 * Backward-compatible queue name wrapper.
 *
 * @deprecated Use {@see FinancialQueue} for all new code. This class remains
 *             to avoid breaking existing integrations that reference PaymentQueue.
 */
class PaymentQueue
{
    public static function high(): string
    {
        return FinancialQueue::paymentsHigh();
    }

    public static function polling(): string
    {
        return FinancialQueue::payments();
    }

    public static function disbursementsHigh(): string
    {
        return FinancialQueue::disbursementsHigh();
    }

    public static function disbursements(): string
    {
        return FinancialQueue::disbursements();
    }
}
