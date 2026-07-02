<?php

namespace App\PaymentPlatform\DTOs;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayRouteKey;

readonly class GatewayRouteResolution
{
    public function __construct(
        public bool $available,
        public ?PaymentGateway $gateway,
        public ?PaymentGatewayRoute $route,
        public ?string $failureReason,
        public bool $fallbackToManual,
        public ?GatewayRouteKey $routeKey,
        public ?string $balanceWarning = null,
    ) {}

    public static function unavailable(
        GatewayRouteKey $routeKey,
        string $reason,
        bool $fallbackToManual = true,
        ?PaymentGatewayRoute $route = null,
    ): self {
        return new self(
            available: false,
            gateway: null,
            route: $route,
            failureReason: $reason,
            fallbackToManual: $fallbackToManual,
            routeKey: $routeKey,
            balanceWarning: null,
        );
    }

    public static function available(
        GatewayRouteKey $routeKey,
        PaymentGatewayRoute $route,
        PaymentGateway $gateway,
        ?string $balanceWarning = null,
    ): self {
        return new self(
            available: true,
            gateway: $gateway,
            route: $route,
            failureReason: null,
            fallbackToManual: (bool) $route->fallback_to_manual,
            routeKey: $routeKey,
            balanceWarning: $balanceWarning,
        );
    }

    public function hasBalanceWarning(): bool
    {
        return filled($this->balanceWarning);
    }
}
