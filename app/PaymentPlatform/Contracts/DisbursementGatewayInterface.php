<?php

namespace App\PaymentPlatform\Contracts;

use App\PaymentPlatform\DTOs\DisburseMoneyRequest;
use App\PaymentPlatform\DTOs\GatewayResult;

interface DisbursementGatewayInterface
{
    public function disburse(DisburseMoneyRequest $request): GatewayResult;
}
