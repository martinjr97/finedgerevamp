<?php

namespace App\Services\Repayments;

use App\Models\Channel;
use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayRoute;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use App\Services\Repayments\DTOs\RepaymentGatewayCollectionPreview;
use App\Services\Repayments\DTOs\RepaymentGatewayCollectionResult;
use App\Services\Repayments\Enums\RepaymentGatewayCollectionStatus;
use App\Support\PhoneNumberFormatter;

class AdminRepaymentGatewayCollectionService
{
    public function __construct(
        private readonly PaymentGatewayRouteService $routeService,
        private readonly GatewayIntegrationService $gatewayIntegrationService,
    ) {}

    public function previewForChannel(Channel $channel, ?float $amount = null, ?string $phone = null): RepaymentGatewayCollectionPreview
    {
        $routeKey = $this->routeService->routeKeyForCollectionChannel($channel);

        if ($routeKey === null) {
            return RepaymentGatewayCollectionPreview::manualOnly($amount, $phone);
        }

        $route = PaymentGatewayRoute::query()
            ->with('paymentGateway')
            ->where('route_key', $routeKey->value)
            ->first();

        $routeLabel = $routeKey->displayLabel();
        $gateway = $route?->paymentGateway;
        $gatewayName = $gateway?->name;
        $linkedAccountLabel = $gateway?->linkedAccountLabel();
        $resolution = $this->routeService->resolveRouteForCollection($channel);

        if (! $resolution->available) {
            return RepaymentGatewayCollectionPreview::notReady(
                routeKey: $routeKey,
                routeLabel: $routeLabel,
                gatewayName: $gatewayName,
                linkedAccountLabel: $linkedAccountLabel,
                customerPhone: $phone,
                amount: $amount,
                reason: $resolution->failureReason ?? 'Gateway collection is not available for this channel.',
                fallbackToManual: $resolution->fallbackToManual,
            );
        }

        if ($routeKey === GatewayRouteKey::WalletCollection) {
            $normalizedPhone = trim((string) ($phone ?? ''));
            if ($normalizedPhone === '') {
                return RepaymentGatewayCollectionPreview::notReady(
                    routeKey: $routeKey,
                    routeLabel: $routeLabel,
                    gatewayName: $gatewayName ?? $resolution->gateway?->name,
                    linkedAccountLabel: $linkedAccountLabel ?? $resolution->gateway?->linkedAccountLabel(),
                    customerPhone: null,
                    amount: $amount,
                    reason: 'A valid customer mobile money number is required for gateway collection.',
                    fallbackToManual: $resolution->fallbackToManual,
                );
            }

            if (! PhoneNumberFormatter::isValid($normalizedPhone)) {
                return RepaymentGatewayCollectionPreview::notReady(
                    routeKey: $routeKey,
                    routeLabel: $routeLabel,
                    gatewayName: $gatewayName ?? $resolution->gateway?->name,
                    linkedAccountLabel: $linkedAccountLabel ?? $resolution->gateway?->linkedAccountLabel(),
                    customerPhone: $normalizedPhone,
                    amount: $amount,
                    reason: PhoneNumberFormatter::diagnose($normalizedPhone),
                    fallbackToManual: $resolution->fallbackToManual,
                );
            }

            $phone = $normalizedPhone;
        }

        return RepaymentGatewayCollectionPreview::ready(
            routeKey: $routeKey,
            routeLabel: $routeLabel,
            gatewayName: $gatewayName ?? $resolution->gateway?->name ?? 'Configured gateway',
            linkedAccountLabel: $linkedAccountLabel ?? $resolution->gateway?->linkedAccountLabel() ?? 'Linked account',
            customerPhone: $phone,
            amount: $amount ?? 0.0,
        );
    }

    /**
     * @return array<int, RepaymentGatewayCollectionPreview>
     */
    public function previewsForChannels(iterable $channels, ?float $amount = null, ?string $phone = null): array
    {
        $previews = [];

        foreach ($channels as $channel) {
            $previews[$channel->id] = $this->previewForChannel($channel, $amount, $phone);
        }

        return $previews;
    }

    public function initiateForRepayment(Repayment $repayment, Channel $channel, ?string $phone): RepaymentGatewayCollectionResult
    {
        $preview = $this->previewForChannel($channel, (float) $repayment->total_amount, $phone);

        if (! $preview->ready) {
            if ($preview->applicable && $preview->reason) {
                return $preview->fallbackToManual
                    ? RepaymentGatewayCollectionResult::fallbackManual($preview->reason)
                    : RepaymentGatewayCollectionResult::failed($preview->reason);
            }

            return RepaymentGatewayCollectionResult::manualPending();
        }

        if ($this->hasActiveCollectionAttempt($repayment)) {
            $message = 'An active gateway collection attempt already exists for this repayment.';

            return $preview->fallbackToManual
                ? RepaymentGatewayCollectionResult::fallbackManual($message)
                : RepaymentGatewayCollectionResult::failed($message);
        }

        $paymentResult = $this->gatewayIntegrationService->initiateCollection($repayment, $channel, $phone);

        if (! ($paymentResult['success'] ?? false)) {
            $reason = $paymentResult['message'] ?? 'Gateway collection could not be initiated.';

            return $preview->fallbackToManual
                ? RepaymentGatewayCollectionResult::fallbackManual($reason)
                : RepaymentGatewayCollectionResult::failed($reason);
        }

        $gatewayMetadata = is_array($paymentResult['metadata'] ?? null) ? $paymentResult['metadata'] : [];

        return RepaymentGatewayCollectionResult::initiated(
            gatewayName: $preview->gatewayName ?? 'gateway',
            gatewayMetadata: $gatewayMetadata,
            reference: $paymentResult['reference'] ?? null,
            transactionId: $paymentResult['transaction_id'] ?? null,
        );
    }

    public function hasActiveCollectionAttempt(Repayment $repayment): bool
    {
        return PaymentGatewayAttempt::query()
            ->where('attemptable_type', Repayment::class)
            ->where('attemptable_id', $repayment->id)
            ->where('direction', GatewayDirection::Collection)
            ->whereIn('status', [
                GatewayAttemptStatus::Created,
                GatewayAttemptStatus::Initiated,
                GatewayAttemptStatus::Pending,
            ])
            ->exists();
    }
}
