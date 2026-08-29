<?php

namespace App\Migration\Replay\Contracts;

use App\Migration\Replay\DTOs\ReplayResult;

interface RepaymentReplayStrategy
{
    public function supports(array $repayment, ?array $customer, ?array $client): bool;

    /**
     * @param  array<string, mixed>  $repayment
     * @param  array<string, mixed>|null  $customer
     * @param  array<string, mixed>|null  $client
     * @param  array<int, array<string, mixed>>  $loanStates  keyed by loan id — mutated in place
     */
    public function replay(array $repayment, ?array $customer, ?array $client, array &$loanStates): ReplayResult;
}
