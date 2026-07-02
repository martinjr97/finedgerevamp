<?php

namespace App\PaymentPlatform\Services;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayRouteKey;

class PaymentGatewayRouteProvisioner
{
    /**
     * Ensure all routing rows exist without overwriting saved configuration.
     */
    public function sync(): void
    {
        $cgrate = PaymentGateway::query()->where('code', 'cgrate')->first();

        foreach (GatewayRouteKey::ordered() as $routeKey) {
            $defaultGatewayId = match ($routeKey) {
                GatewayRouteKey::WalletCollection,
                GatewayRouteKey::WalletDisbursement,
                GatewayRouteKey::BankDisbursement => $cgrate?->id,
                default => null,
            };

            PaymentGatewayRoute::firstOrCreate(
                ['route_key' => $routeKey->value],
                [
                    'payment_gateway_id' => $defaultGatewayId,
                    'enabled' => false,
                    'auto_process' => false,
                    'fallback_to_manual' => true,
                    'notes' => null,
                ]
            );
        }
    }
}
