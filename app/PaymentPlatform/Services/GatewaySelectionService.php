<?php

namespace App\PaymentPlatform\Services;

use App\Models\Channel;
use App\Models\Loan;
use App\Models\PaymentGateway;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;

class GatewaySelectionService
{
    public function __construct(
        private readonly PaymentGatewayRouteService $routeService,
    ) {}

    /**
     * Select the gateway configured for this collection route.
     */
    public function selectForCollection(
        Channel $channel,
        bool $requireLinkedAccount = false,
        ?int $loanProductId = null,
    ): ?PaymentGateway {
        if ($this->mapChannelToPaymentMethod($channel) === null) {
            return null;
        }

        $resolution = $this->routeService->resolveRouteForCollection($channel, $loanProductId);

        if (! $resolution->available) {
            return null;
        }

        $gateway = $resolution->gateway;

        if ($requireLinkedAccount && $gateway && ! $gateway->hasLinkedFinancialAccount()) {
            return null;
        }

        return $gateway;
    }

    /**
     * Select the gateway configured for this disbursement route.
     */
    public function selectForDisbursement(Loan $loan, bool $requireLinkedAccount = true): ?PaymentGateway
    {
        $resolution = $this->routeService->resolveRouteForDisbursement($loan);

        if (! $resolution->available) {
            return null;
        }

        $gateway = $resolution->gateway;

        if ($requireLinkedAccount && $gateway && ! $gateway->hasLinkedFinancialAccount()) {
            return null;
        }

        return $gateway;
    }

    public function selectForEmployeeLoanDisbursement(\App\Models\EmployeeLoan $loan, bool $requireLinkedAccount = true): ?PaymentGateway
    {
        $resolution = $this->routeService->resolveRouteForEmployeeLoanDisbursement($loan);

        if (! $resolution->available) {
            return null;
        }

        $gateway = $resolution->gateway;

        if ($requireLinkedAccount && $gateway && ! $gateway->hasLinkedFinancialAccount()) {
            return null;
        }

        return $gateway;
    }

    public function mapChannelToPaymentMethod(Channel $channel): ?GatewayPaymentMethod
    {
        return match ($channel->type) {
            Channel::TYPE_MOBILE_WALLET => GatewayPaymentMethod::MobileMoney,
            Channel::TYPE_BANK => GatewayPaymentMethod::Bank,
            Channel::TYPE_CASH => GatewayPaymentMethod::Manual,
            default => null,
        };
    }
}
