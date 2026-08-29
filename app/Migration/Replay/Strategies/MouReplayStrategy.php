<?php

namespace App\Migration\Replay\Strategies;

/**
 * MOU/salary loans for salary_based clients.
 * Legacy routes these through executeMouLoanRepayment(); eligibility uses isMouLoan().
 */
class MouReplayStrategy extends AccrualReplayStrategy
{
    public function supports(array $repayment, ?array $customer, ?array $client): bool
    {
        if ($customer && ($customer['is_marketize_customer'] ?? false)) {
            return false;
        }

        return ($client['product_type'] ?? null) === 'salary_based';
    }

    protected function rulePrefix(): string
    {
        return 'mou';
    }
}
