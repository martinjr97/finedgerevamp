<?php

namespace App\Migration\Replay\Support;

use App\Migration\RepaymentAttributionService;

class LegacyRepaymentContext
{
    public function __construct(
        private readonly RepaymentAttributionService $attribution,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $loanStates
     * @return list<array<string, mixed>>
     */
    public function eligibleActiveLoans(array $loanStates, string $paymentAt, ?int $userId = null): array
    {
        return array_values(array_filter($loanStates, function (array $state) use ($paymentAt, $userId) {
            if ($userId !== null && (int) ($state['user_id'] ?? 0) !== $userId) {
                return false;
            }
            if (($state['created_at'] ?? '') > $paymentAt) {
                return false;
            }
            if ($state['settled_before_payment'] ?? false) {
                return false;
            }

            return ($state['status'] ?? '301') === '301';
        }));
    }

    /**
     * @param  array<string, mixed>  $repayment
     * @param  list<array<string, mixed>>  $eligibleLoans
     * @param  array<int, array<string, mixed>>  $allLoansById
     * @param  int  $userId
     */
    public function validateAffectedLoanIds(array $repayment, array $eligibleLoans, array $allLoansById, int $userId): ?array
    {
        if (! $this->attribution->hasPopulatedAffectedLoanIds($repayment)) {
            return null;
        }

        $parsed = $this->attribution->parseAffectedLoanIds($repayment);
        $valid = [];
        foreach ($parsed as $item) {
            $loanId = (int) ($item['loan_id'] ?? 0);
            $amount = (float) ($item['amount_applied'] ?? 0);
            if ($loanId <= 0 || $amount <= 0) {
                continue;
            }
            $loan = $allLoansById[$loanId] ?? null;
            if (! $loan || (int) ($loan['user_id'] ?? 0) !== $userId) {
                return null;
            }
            $valid[] = ['loan_id' => $loanId, 'amount_applied' => $amount];
        }

        if ($valid === []) {
            return null;
        }

        $repaymentTotal = (float) ($repayment['repayment_amount'] ?? 0);
        $allocatedTotal = array_sum(array_column($valid, 'amount_applied'));
        if (abs($repaymentTotal - $allocatedTotal) > 0.01) {
            return null;
        }

        return $valid;
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    public function characterBalance(array $loan): float
    {
        return max(0, (float) ($loan['loan_amount'] ?? 0) - (float) ($loan['repaid_amount'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    public function mouBalance(array $loan): float
    {
        return max(0, (float) ($loan['current_loan_amount'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    public function componentSplit(array $loan, float $amountApplied): array
    {
        $principal = (float) ($loan['obtained_amount'] ?? 0);
        $total = (float) ($loan['loan_amount'] ?? 0);
        $interest = max(0, $total - $principal);
        $expected = $principal + $interest;
        if ($expected <= 0) {
            return ['principal' => $amountApplied, 'interest' => 0.0];
        }

        $ratioP = $principal / $expected;

        return [
            'principal' => round($amountApplied * $ratioP, 2),
            'interest' => round($amountApplied * (1 - $ratioP), 2),
        ];
    }
}
