<?php

namespace App\Migration\Replay\Strategies;

use App\Migration\RepaymentAttributionService;
use App\Migration\Replay\Contracts\RepaymentReplayStrategy;
use App\Migration\Replay\DTOs\ReplayAllocation;
use App\Migration\Replay\DTOs\ReplayResult;
use App\Migration\Replay\Support\LegacyRepaymentContext;

class MarketizeReplayStrategy implements RepaymentReplayStrategy
{
    public function __construct(
        private readonly LegacyRepaymentContext $context,
        private readonly RepaymentAttributionService $attribution,
    ) {}

    public function supports(array $repayment, ?array $customer, ?array $client): bool
    {
        return ($customer['is_marketize_customer'] ?? false)
            || (($client['product_type'] ?? null) === 'marketize_based');
    }

    public function replay(array $repayment, ?array $customer, ?array $client, array &$loanStates): ReplayResult
    {
        $userId = (int) $repayment['user_id'];
        $paymentAt = (string) $repayment['created_at'];
        $amount = (float) $repayment['repayment_amount'];
        $allLoansById = $loanStates;

        $affected = $this->context->validateAffectedLoanIds(
            $repayment,
            $this->context->eligibleActiveLoans($loanStates, $paymentAt, $userId),
            $allLoansById,
            $userId
        );

        if ($affected !== null) {
            return $this->applyDirect($repayment, $affected, $loanStates, $userId);
        }

        $eligible = $this->context->eligibleActiveLoans($loanStates, $paymentAt, $userId);
        if (count($eligible) === 0) {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::D_MANUAL,
                confidence: 'LOW',
                ruleUsed: 'marketize_no_eligible_loan',
                allocations: [],
                exception: 'no_eligible_marketize_loan',
            );
        }

        if (count($eligible) > 1) {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::C_AMBIGUOUS,
                confidence: 'LOW',
                ruleUsed: 'marketize_multi_active',
                allocations: [],
                exception: 'multiple active marketize loans',
            );
        }

        $loanId = (int) $eligible[0]['id'];
        $before = max(0, (float) ($loanStates[$loanId]['loan_amount'] ?? 0) - (float) ($loanStates[$loanId]['repaid_amount'] ?? 0));
        $loanStates[$loanId]['repaid_amount'] = (float) ($loanStates[$loanId]['repaid_amount'] ?? 0) + $amount;
        if ($before - $amount <= 0.01) {
            $loanStates[$loanId]['status'] = '300';
        }
        $after = max(0, (float) ($loanStates[$loanId]['loan_amount'] ?? 0) - (float) ($loanStates[$loanId]['repaid_amount'] ?? 0));
        $split = $this->context->componentSplit($loanStates[$loanId], $amount);

        return new ReplayResult(
            legacyRepaymentId: (int) $repayment['id'],
            classification: RepaymentAttributionService::B_RECONSTRUCTED,
            confidence: 'HIGH',
            ruleUsed: 'marketize_single_active_loan',
            allocations: [
                new ReplayAllocation(
                    legacyRepaymentId: (int) $repayment['id'],
                    legacyLoanId: $loanId,
                    legacyUserId: $userId,
                    allocatedAmount: $amount,
                    principalAmount: $split['principal'],
                    interestAmount: $split['interest'],
                    classification: RepaymentAttributionService::B_RECONSTRUCTED,
                    confidence: 'HIGH',
                    ruleUsed: 'marketize_single_active_loan',
                    balanceBefore: $before,
                    balanceAfter: $after,
                ),
            ],
        );
    }

    /**
     * @param  list<array{loan_id: int, amount_applied: float}>  $affected
     * @param  array<int, array<string, mixed>>  $loanStates
     */
    private function applyDirect(array $repayment, array $affected, array &$loanStates, int $userId): ReplayResult
    {
        $allocations = [];
        foreach ($affected as $item) {
            $loanId = $item['loan_id'];
            $apply = $item['amount_applied'];
            $before = max(0, (float) ($loanStates[$loanId]['loan_amount'] ?? 0) - (float) ($loanStates[$loanId]['repaid_amount'] ?? 0));
            $loanStates[$loanId]['repaid_amount'] = (float) ($loanStates[$loanId]['repaid_amount'] ?? 0) + $apply;
            $allocations[] = new ReplayAllocation(
                legacyRepaymentId: (int) $repayment['id'],
                legacyLoanId: $loanId,
                legacyUserId: $userId,
                allocatedAmount: $apply,
                principalAmount: (float) ($repayment['principal_amount'] ?? 0),
                interestAmount: (float) ($repayment['interest_amount'] ?? 0),
                classification: RepaymentAttributionService::A_DIRECT,
                confidence: 'HIGH',
                ruleUsed: 'marketize_affected_loan_ids',
                balanceBefore: $before,
                balanceAfter: max(0, $before - $apply),
            );
        }

        return new ReplayResult(
            legacyRepaymentId: (int) $repayment['id'],
            classification: RepaymentAttributionService::A_DIRECT,
            confidence: 'HIGH',
            ruleUsed: 'marketize_affected_loan_ids',
            allocations: $allocations,
        );
    }
}
