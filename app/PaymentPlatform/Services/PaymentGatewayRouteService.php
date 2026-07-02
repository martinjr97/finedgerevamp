<?php

namespace App\PaymentPlatform\Services;

use App\Models\Channel;
use App\Models\Loan;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\DTOs\GatewayRouteResolution;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Support\CGrateIssuerNameResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentGatewayRouteService
{
    public function __construct(
        private readonly CGrateIssuerNameResolver $issuerNameResolver,
    ) {}

    public function resolveRoute(GatewayRouteKey $routeKey, ?float $amount = null, ?Loan $loan = null): GatewayRouteResolution
    {
        $route = PaymentGatewayRoute::query()
            ->with('paymentGateway')
            ->where('route_key', $routeKey->value)
            ->first();

        if (! $route) {
            return GatewayRouteResolution::unavailable($routeKey, 'Payment route is not configured.');
        }

        $fallback = (bool) $route->fallback_to_manual;

        if (! $route->enabled) {
            return GatewayRouteResolution::unavailable(
                $routeKey,
                'Payment route is disabled.',
                $fallback,
                $route,
            );
        }

        if (! $route->payment_gateway_id) {
            return GatewayRouteResolution::unavailable(
                $routeKey,
                'No payment gateway is assigned to this route.',
                $fallback,
                $route,
            );
        }

        $gateway = $route->paymentGateway;
        if (! $gateway) {
            return GatewayRouteResolution::unavailable(
                $routeKey,
                'Assigned payment gateway could not be found.',
                $fallback,
                $route,
            );
        }

        $capabilityFailure = $this->validateGatewayCapabilities($routeKey, $gateway);
        if ($capabilityFailure !== null) {
            return GatewayRouteResolution::unavailable($routeKey, $capabilityFailure, $fallback, $route);
        }

        if ($routeKey->direction()->value === 'collection' && ! $gateway->isAvailableForCollection()) {
            return GatewayRouteResolution::unavailable(
                $routeKey,
                'Assigned gateway is not available for collections.',
                $fallback,
                $route,
            );
        }

        if ($routeKey->direction()->value === 'disbursement' && ! $gateway->isAvailableForDisbursement()) {
            return GatewayRouteResolution::unavailable(
                $routeKey,
                'Assigned gateway is not available for disbursements.',
                $fallback,
                $route,
            );
        }

        if (! $gateway->hasLinkedFinancialAccount()) {
            return GatewayRouteResolution::unavailable(
                $routeKey,
                'Assigned gateway has no linked financial account.',
                $fallback,
                $route,
            );
        }

        if ($loan !== null && $routeKey->direction()->value === 'disbursement') {
            try {
                $this->issuerNameResolver->resolveForLoan($loan);
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?? 'Loan disbursement destination is invalid.';

                return GatewayRouteResolution::unavailable($routeKey, $message, $fallback, $route);
            }
        }

        $balanceWarning = null;

        if ($routeKey->direction()->value === 'disbursement' && $amount !== null) {
            $balance = $gateway->linkedAccountBalance();
            if ($balance !== null && $balance < $amount) {
                $balanceWarning = sprintf(
                    'System balance (ZMW %s) is below the disbursement amount (ZMW %s). The gateway request will still be sent; the provider may accept or reject it.',
                    number_format($balance, 2),
                    number_format($amount, 2),
                );
            }
        }

        return GatewayRouteResolution::available($routeKey, $route, $gateway, $balanceWarning);
    }

    public function resolveRouteForCollection(Channel $channel): GatewayRouteResolution
    {
        $routeKey = $this->routeKeyForCollectionChannel($channel);

        if ($routeKey === null) {
            return GatewayRouteResolution::unavailable(
                GatewayRouteKey::WalletCollection,
                'This channel type does not support automated gateway collection.',
            );
        }

        return $this->resolveRoute($routeKey);
    }

    public function resolveRouteForDisbursement(Loan $loan): GatewayRouteResolution
    {
        try {
            $resolved = $this->issuerNameResolver->resolveForLoan($loan);
        } catch (ValidationException $e) {
            $routeKey = $this->guessDisbursementRouteKey($loan);

            return GatewayRouteResolution::unavailable(
                $routeKey ?? GatewayRouteKey::WalletDisbursement,
                collect($e->errors())->flatten()->first() ?? 'Loan disbursement destination is invalid.',
            );
        }

        $routeKey = match ($resolved['payment_method']) {
            'bank' => GatewayRouteKey::BankDisbursement,
            default => GatewayRouteKey::WalletDisbursement,
        };

        return $this->resolveRoute($routeKey, (float) $loan->principal_amount, $loan);
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function eligibleGateways(GatewayRouteKey $routeKey): Collection
    {
        return PaymentGateway::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->filter(fn (PaymentGateway $gateway) => $this->gatewayEligibleForRoute($routeKey, $gateway))
            ->values();
    }

    public function gatewayEligibleForRoute(GatewayRouteKey $routeKey, PaymentGateway $gateway): bool
    {
        return $this->validateGatewayCapabilities($routeKey, $gateway) === null;
    }

    public function routeKeyForCollectionChannel(Channel $channel): ?GatewayRouteKey
    {
        return match ($channel->type) {
            Channel::TYPE_MOBILE_WALLET => GatewayRouteKey::WalletCollection,
            Channel::TYPE_BANK => GatewayRouteKey::BankCollection,
            default => null,
        };
    }

    private function guessDisbursementRouteKey(Loan $loan): ?GatewayRouteKey
    {
        $loan->loadMissing('channel');

        $channelType = $loan->disbursement_channel_type ?: $loan->channel?->type;

        return match ($channelType) {
            Channel::TYPE_BANK => GatewayRouteKey::BankDisbursement,
            Channel::TYPE_MOBILE_WALLET => GatewayRouteKey::WalletDisbursement,
            default => null,
        };
    }

    private function validateGatewayCapabilities(GatewayRouteKey $routeKey, PaymentGateway $gateway): ?string
    {
        $required = $routeKey->requiredGatewayCapabilities();

        if ($required['collections'] && ! $gateway->supports_collections) {
            return 'Gateway does not support collections.';
        }

        if ($required['disbursements'] && ! $gateway->supports_disbursements) {
            return 'Gateway does not support disbursements.';
        }

        if ($required['mobile_money'] && ! $gateway->supports_mobile_money) {
            return 'Gateway does not support mobile money.';
        }

        if ($required['bank'] && ! $gateway->supports_bank) {
            return 'Gateway does not support bank transfers.';
        }

        try {
            $provider = $gateway->resolveProvider();
            if (! $provider->supports($routeKey->paymentMethod()->value, $routeKey->direction()->value)) {
                return 'Gateway provider does not support this payment method for the selected route.';
            }
        } catch (\Throwable) {
            return 'Gateway provider could not be resolved.';
        }

        return null;
    }
}
