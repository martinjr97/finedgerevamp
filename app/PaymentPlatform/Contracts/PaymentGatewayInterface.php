<?php

namespace App\PaymentPlatform\Contracts;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\DTOs\CollectMoneyRequest;
use App\PaymentPlatform\DTOs\GatewayResult;
use App\PaymentPlatform\DTOs\GatewayStatusResult;

interface PaymentGatewayInterface
{
    public function collect(CollectMoneyRequest $request): GatewayResult;

    public function queryStatus(PaymentGatewayAttempt $attempt): GatewayStatusResult;

    public function supports(string $paymentMethod, string $direction): bool;
}
