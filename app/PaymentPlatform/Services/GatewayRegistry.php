<?php

namespace App\PaymentPlatform\Services;

use App\Models\PaymentGateway;

class GatewayRegistry
{
    public function findByCode(string $code): ?PaymentGateway
    {
        return PaymentGateway::query()->where('code', $code)->first();
    }

    public function resolveProvider(string $code): \App\PaymentPlatform\Contracts\PaymentGatewayInterface
    {
        $gateway = $this->findByCode($code);

        if (! $gateway) {
            throw new \RuntimeException("Payment gateway [{$code}] not found.");
        }

        return $gateway->resolveProvider();
    }
}
