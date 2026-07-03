<?php

namespace App\Support\Queue;

use App\PaymentPlatform\Enums\FinancialJobPriority;
use App\PaymentPlatform\Enums\GatewayDirection;

class FinancialQueue
{
    public static function connection(): string
    {
        return (string) config('queues.connections.financial', 'redis-financial');
    }

    public static function paymentsHigh(): string
    {
        return (string) config('queues.names.payments_high', 'payments-high');
    }

    public static function payments(): string
    {
        return (string) config('queues.names.payments', 'payments');
    }

    public static function disbursementsHigh(): string
    {
        return (string) config('queues.names.disbursements_high', 'disbursements-high');
    }

    public static function disbursements(): string
    {
        return (string) config('queues.names.disbursements', 'disbursements');
    }

    /**
     * @return list<string>
     */
    public static function allFinancialQueueNames(): array
    {
        return [
            self::paymentsHigh(),
            self::payments(),
            self::disbursementsHigh(),
            self::disbursements(),
        ];
    }

    public static function initiationQueueFor(GatewayDirection $direction): string
    {
        return $direction === GatewayDirection::Disbursement
            ? self::disbursementsHigh()
            : self::paymentsHigh();
    }

    public static function pollingQueueFor(GatewayDirection $direction): string
    {
        return $direction === GatewayDirection::Disbursement
            ? self::disbursements()
            : self::payments();
    }

    public static function callbackQueueFor(GatewayDirection $direction): string
    {
        return self::initiationQueueFor($direction);
    }

    public static function queueFor(GatewayDirection $direction, FinancialJobPriority $priority): string
    {
        return $priority === FinancialJobPriority::High
            ? self::callbackQueueFor($direction)
            : self::pollingQueueFor($direction);
    }

    /**
     * @deprecated Use initiationQueueFor() or pollingQueueFor() explicitly.
     */
    public static function statusQueueFor(GatewayDirection $direction): string
    {
        return self::pollingQueueFor($direction);
    }
}
