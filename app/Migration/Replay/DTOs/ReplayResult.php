<?php

namespace App\Migration\Replay\DTOs;

class ReplayResult
{
    /**
     * @param  list<ReplayAllocation>  $allocations
     */
    public function __construct(
        public readonly int $legacyRepaymentId,
        public readonly string $classification,
        public readonly string $confidence,
        public readonly string $ruleUsed,
        public readonly array $allocations,
        public readonly ?string $exception = null,
        public readonly array $rawContext = [],
    ) {}

    public function totalAllocated(): float
    {
        return round(array_sum(array_map(fn (ReplayAllocation $a) => $a->allocatedAmount, $this->allocations)), 2);
    }
}
