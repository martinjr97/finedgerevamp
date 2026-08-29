<?php

namespace App\Migration\Replay\Strategies;

/**
 * Government loans share the legacy MOU repayment path for salary_based clients
 * but are tracked separately for audit and reconciliation reporting.
 */
class GovernmentReplayStrategy extends AccrualReplayStrategy
{
    public function supports(array $repayment, ?array $customer, ?array $client): bool
    {
        return false;
    }

    protected function rulePrefix(): string
    {
        return 'government';
    }
}
