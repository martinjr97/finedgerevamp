<?php

namespace App\Migration\Replay\Strategies;

/**
 * Legacy routes all salary_based clients through executeMouLoanRepayment(),
 * which selects Loans::where(status_code, 301)->first() among ALL active loans.
 * Eligibility for replay uses RepaymentAttributionService::isMouLoan().
 */
class SalaryBasedClientReplayStrategy extends AccrualReplayStrategy
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
        return 'salary_based_accrual';
    }
}
