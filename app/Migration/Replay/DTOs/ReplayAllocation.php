<?php

namespace App\Migration\Replay\DTOs;

class ReplayAllocation
{
    public function __construct(
        public readonly int $legacyRepaymentId,
        public readonly int $legacyLoanId,
        public readonly int $legacyUserId,
        public readonly float $allocatedAmount,
        public readonly ?float $principalAmount = null,
        public readonly ?float $interestAmount = null,
        public readonly ?float $feeAmount = null,
        public readonly ?float $penaltyAmount = null,
        public readonly string $classification = 'B_RECONSTRUCTED',
        public readonly string $confidence = 'HIGH',
        public readonly string $ruleUsed = '',
        public readonly ?float $balanceBefore = null,
        public readonly ?float $balanceAfter = null,
        public readonly array $rawContext = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'legacy_repayment_id' => $this->legacyRepaymentId,
            'legacy_loan_id' => $this->legacyLoanId,
            'legacy_user_id' => $this->legacyUserId,
            'allocated_amount' => $this->allocatedAmount,
            'principal_amount' => $this->principalAmount,
            'interest_amount' => $this->interestAmount,
            'fee_amount' => $this->feeAmount,
            'penalty_amount' => $this->penaltyAmount,
            'classification' => $this->classification,
            'confidence' => $this->confidence,
            'rule_used' => $this->ruleUsed,
            'balance_before' => $this->balanceBefore,
            'balance_after' => $this->balanceAfter,
            'raw_context' => $this->rawContext,
        ];
    }
}
