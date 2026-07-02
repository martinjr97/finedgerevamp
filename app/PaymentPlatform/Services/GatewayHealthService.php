<?php

namespace App\PaymentPlatform\Services;

use App\Models\PaymentGateway;

class GatewayHealthService
{
    public function isHealthy(PaymentGateway $gateway): bool
    {
        if (! $gateway->status->isOperational()) {
            return false;
        }

        if (! $gateway->isProviderEnabled()) {
            return false;
        }

        return true;
    }

    public function check(PaymentGateway $gateway): array
    {
        return [
            'code' => $gateway->code,
            'healthy' => $this->isHealthy($gateway),
            'status' => $gateway->status->value,
            'provider_enabled' => $gateway->isProviderEnabled(),
        ];
    }
}
