<?php

namespace App\Migration\Replay\Strategies;

use App\Migration\RepaymentAttributionService;
use App\Migration\Replay\Contracts\RepaymentReplayStrategy;
use App\Migration\Replay\DTOs\ReplayAllocation;
use App\Migration\Replay\DTOs\ReplayResult;
use App\Migration\Replay\Support\LegacyRepaymentContext;

class CharacterReplayStrategy implements RepaymentReplayStrategy
{
    public function __construct(
        private readonly LegacyRepaymentContext $context,
        private readonly RepaymentAttributionService $attribution,
    ) {}

    public function supports(array $repayment, ?array $customer, ?array $client): bool
    {
        if ($customer && ($customer['is_marketize_customer'] ?? false)) {
            return false;
        }
        if (($client['product_type'] ?? null) === 'salary_based') {
            return false;
        }

        return true;
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
        usort($eligible, fn ($a, $b) => strcmp((string) ($a['due_date'] ?? ''), (string) ($b['due_date'] ?? '')));

        $remaining = $amount;
        $allocations = [];

        foreach ($eligible as $loan) {
            if ($remaining <= 0) {
                break;
            }
            $loanId = (int) $loan['id'];
            $balance = $this->context->characterBalance($loanStates[$loanId]);
            if ($balance <= 0) {
                continue;
            }
            $apply = min($remaining, $balance);
            $before = $balance;
            $loanStates[$loanId]['repaid_amount'] = (float) ($loanStates[$loanId]['repaid_amount'] ?? 0) + $apply;
            if ($this->context->characterBalance($loanStates[$loanId]) <= 0.01) {
                $loanStates[$loanId]['status'] = '300';
            }
            $split = $this->context->componentSplit($loanStates[$loanId], $apply);
            $allocations[] = new ReplayAllocation(
                legacyRepaymentId: (int) $repayment['id'],
                legacyLoanId: $loanId,
                legacyUserId: $userId,
                allocatedAmount: $apply,
                principalAmount: $split['principal'],
                interestAmount: $split['interest'],
                classification: RepaymentAttributionService::B_RECONSTRUCTED,
                confidence: 'HIGH',
                ruleUsed: 'character_due_date_waterfall',
                balanceBefore: $before,
                balanceAfter: $this->context->characterBalance($loanStates[$loanId]),
            );
            $remaining -= $apply;
        }

        if ($allocations === []) {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::D_MANUAL,
                confidence: 'LOW',
                ruleUsed: 'character_no_eligible_balance',
                allocations: [],
                exception: 'no_eligible_character_loan_balance',
            );
        }

        if ($remaining > 0.01) {
            return new ReplayResult(
                legacyRepaymentId: (int) $repayment['id'],
                classification: RepaymentAttributionService::D_MANUAL,
                confidence: 'LOW',
                ruleUsed: 'character_unallocated_remainder',
                allocations: [],
                exception: 'waterfall could not allocate full repayment amount',
                rawContext: ['remaining' => $remaining, 'partial_allocations' => count($allocations)],
            );
        }

        return new ReplayResult(
            legacyRepaymentId: (int) $repayment['id'],
            classification: RepaymentAttributionService::B_RECONSTRUCTED,
            confidence: 'HIGH',
            ruleUsed: 'character_due_date_waterfall',
            allocations: $allocations,
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
            $before = $this->context->characterBalance($loanStates[$loanId]);
            $loanStates[$loanId]['repaid_amount'] = (float) ($loanStates[$loanId]['repaid_amount'] ?? 0) + $apply;
            if ($this->context->characterBalance($loanStates[$loanId]) <= 0.01) {
                $loanStates[$loanId]['status'] = '300';
            }
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
                ruleUsed: 'affected_loan_ids',
                balanceBefore: $before,
                balanceAfter: $this->context->characterBalance($loanStates[$loanId]),
            );
        }

        return new ReplayResult(
            legacyRepaymentId: (int) $repayment['id'],
            classification: RepaymentAttributionService::A_DIRECT,
            confidence: 'HIGH',
            ruleUsed: 'affected_loan_ids',
            allocations: $allocations,
        );
    }
}
