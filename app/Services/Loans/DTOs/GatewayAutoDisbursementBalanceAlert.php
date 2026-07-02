<?php

namespace App\Services\Loans\DTOs;

readonly class GatewayAutoDisbursementBalanceAlert
{
    public function __construct(
        public string $routeLabel,
        public string $gatewayName,
        public string $linkedAccountLabel,
        public string $accountType,
        public float $systemBalance,
        public float $exposureAmount,
        public string $message,
        public ?string $manageUrl = null,
    ) {}

    public function formattedBalance(): string
    {
        return 'ZMW '.number_format($this->systemBalance, 2);
    }

    public function formattedExposure(): string
    {
        return 'ZMW '.number_format($this->exposureAmount, 2);
    }
}
