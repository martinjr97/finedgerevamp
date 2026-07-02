<?php

namespace Tests\Support;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use Database\Seeders\PaymentGatewayRouteSeeder;

trait EnablesPaymentGatewayRoutes
{
    protected function seedPaymentGatewayRoutes(): void
    {
        $this->seed(PaymentGatewayRouteSeeder::class);
    }

    protected function enablePaymentGatewayRoute(
        GatewayRouteKey $routeKey,
        ?int $gatewayId = null,
        bool $autoProcess = false,
    ): PaymentGatewayRoute {
        $gatewayId ??= PaymentGateway::query()->where('code', 'cgrate')->value('id');

        $route = PaymentGatewayRoute::query()->where('route_key', $routeKey->value)->firstOrFail();
        $route->update([
            'payment_gateway_id' => $gatewayId,
            'enabled' => true,
            'auto_process' => $autoProcess,
        ]);

        return $route->fresh();
    }
}
