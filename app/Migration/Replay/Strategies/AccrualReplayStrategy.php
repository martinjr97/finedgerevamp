<?php

namespace App\Migration\Replay\Strategies;

use App\Migration\RepaymentAttributionService;
use App\Migration\Replay\Contracts\RepaymentReplayStrategy;
use App\Migration\Replay\DTOs\ReplayAllocation;
use App\Migration\Replay\DTOs\ReplayResult;
use App\Migration\Replay\Support\LegacyRepaymentContext;

abstract class AccrualReplayStrategy implements RepaymentReplayStrategy
{
    public function __construct(
        protected readonly LegacyRepaymentContext $context,
        protected readonly RepaymentAttributionService $attribution,
    ) {}

    abstract protected function rulePrefix(): string;

    public function replay(array $repayment, ?array $customer, ?array $client, array &$loanStates): ReplayResult
    {
        $userId = (int) $repayment['user_id'];
        $paymentAt = (string) $repayment['created_at'];
        $amount = (float) $repayment['repayment_amount'];
        $allLoansById = $loanStates;

        $eligible = $this->context->eligibleActiveLoans($loanStates, $paymentAt, $userId);
        $mouEligible = $this->mouEligibleLoans($eligible, $client);

        $affected = $this->context->validateAffectedLoanIds(
            $repayment,
            $eligible,
            $allLoansById,
            $userId
        );

        if ($affected !== null) {
            return $this->applyDirect($repayment, $affected, $loanStates, $userId);
        }

        if (count($eligible) === 0) {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::D_MANUAL,
                confidence: 'LOW',
                ruleUsed: $this->rulePrefix().'_no_eligible_loan',
                allocations: [],
                exception: 'no_eligible_accrual_loan',
            );
        }

        if (count($mouEligible) > 1) {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::C_AMBIGUOUS,
                confidence: 'LOW',
                ruleUsed: $this->rulePrefix().'_multi_active_no_attribution',
                allocations: [],
                exception: 'multiple eligible MOU loans without affected_loan_ids',
                rawContext: ['eligible_loan_ids' => array_column($mouEligible, 'id')],
            );
        }

        if (count($mouEligible) === 1) {
            $loan = $mouEligible[0];
        } elseif (count($eligible) === 1) {
            $loan = $eligible[0];
        } else {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::C_AMBIGUOUS,
                confidence: 'LOW',
                ruleUsed: $this->rulePrefix().'_multi_active_no_attribution',
                allocations: [],
                exception: 'multiple active loans without reliable MOU attribution',
                rawContext: ['eligible_loan_ids' => array_column($eligible, 'id')],
            );
        }

        $loanId = (int) $loan['id'];
        $before = $this->context->mouBalance($loanStates[$loanId]);
        $this->applyMouCash($loanStates[$loanId], $amount);
        $split = $this->context->componentSplit($loanStates[$loanId], $amount);

        return new ReplayResult(
            legacyRepaymentId: (int) $repayment['id'],
            classification: RepaymentAttributionService::B_RECONSTRUCTED,
            confidence: 'HIGH',
            ruleUsed: $this->rulePrefix().'_single_eligible_loan',
            allocations: [
                new ReplayAllocation(
                    legacyRepaymentId: (int) $repayment['id'],
                    legacyLoanId: $loanId,
                    legacyUserId: $userId,
                    allocatedAmount: $amount,
                    principalAmount: (float) ($repayment['principal_amount'] ?? $split['principal']),
                    interestAmount: (float) ($repayment['interest_amount'] ?? $split['interest']),
                    classification: RepaymentAttributionService::B_RECONSTRUCTED,
                    confidence: 'HIGH',
                    ruleUsed: $this->rulePrefix().'_single_eligible_loan',
                    balanceBefore: $before,
                    balanceAfter: $this->context->mouBalance($loanStates[$loanId]),
                ),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $eligible
     * @param  array<string, mixed>|null  $client
     * @return list<array<string, mixed>>
     */
    protected function mouEligibleLoans(array $eligible, ?array $client): array
    {
        return array_values(array_filter(
            $eligible,
            fn (array $loan) => $this->attribution->isMouLoan($loan, $client)
        ));
    }

    /**
     * @param  array<string, mixed>  $loanState
     */
    protected function applyMouCash(array &$loanState, float $amount): void
    {
        $loanState['current_loan_amount'] = max(0, (float) ($loanState['current_loan_amount'] ?? 0) - $amount);
        $loanState['repaid_amount'] = (float) ($loanState['repaid_amount'] ?? 0) + $amount;
        if ($this->context->mouBalance($loanState) <= 0.01) {
            $loanState['status'] = '300';
            $loanState['current_loan_amount'] = 0;
        }
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
            if (! isset($loanStates[$loanId])) {
                continue;
            }
            $apply = $item['amount_applied'];
            $before = $this->context->mouBalance($loanStates[$loanId]);
            $this->applyMouCash($loanStates[$loanId], $apply);
            $split = $this->context->componentSplit($loanStates[$loanId], $apply);
            $allocations[] = new ReplayAllocation(
                legacyRepaymentId: (int) $repayment['id'],
                legacyLoanId: $loanId,
                legacyUserId: $userId,
                allocatedAmount: $apply,
                principalAmount: (float) ($repayment['principal_amount'] ?? $split['principal']),
                interestAmount: (float) ($repayment['interest_amount'] ?? $split['interest']),
                classification: RepaymentAttributionService::A_DIRECT,
                confidence: 'HIGH',
                ruleUsed: $this->rulePrefix().'_affected_loan_ids',
                balanceBefore: $before,
                balanceAfter: $this->context->mouBalance($loanStates[$loanId]),
            );
        }

        return new ReplayResult(
            legacyRepaymentId: (int) $repayment['id'],
            classification: RepaymentAttributionService::A_DIRECT,
            confidence: 'HIGH',
            ruleUsed: $this->rulePrefix().'_affected_loan_ids',
            allocations: $allocations,
        );
    }
}
