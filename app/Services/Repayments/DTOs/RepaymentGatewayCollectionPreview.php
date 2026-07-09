<?php

namespace App\Services\Repayments\DTOs;

use App\PaymentPlatform\Enums\GatewayRouteKey;

readonly class RepaymentGatewayCollectionPreview
{
    /**
     * @param  'ready'|'not_ready'|'manual'|'unsupported'  $statusType
     */
    public function __construct(
        public bool $applicable,
        public bool $ready,
        public ?GatewayRouteKey $routeKey,
        public ?string $routeLabel,
        public ?string $gatewayName,
        public ?string $linkedAccountLabel,
        public ?string $customerPhone,
        public ?float $amount,
        public ?string $statusLabel,
        public string $statusType,
        public ?string $reason,
        public bool $fallbackToManual,
    ) {}

    public static function unsupported(string $channelName): self
    {
        return new self(
            applicable: false,
            ready: false,
            routeKey: null,
            routeLabel: null,
            gatewayName: null,
            linkedAccountLabel: null,
            customerPhone: null,
            amount: null,
            statusLabel: 'Unsupported',
            statusType: 'unsupported',
            reason: $channelName.' does not support automated gateway collection.',
            fallbackToManual: true,
        );
    }

    public static function manualOnly(?float $amount = null, ?string $customerPhone = null): self
    {
        return new self(
            applicable: false,
            ready: false,
            routeKey: null,
            routeLabel: null,
            gatewayName: null,
            linkedAccountLabel: null,
            customerPhone: $customerPhone,
            amount: $amount,
            statusLabel: 'Manual',
            statusType: 'manual',
            reason: null,
            fallbackToManual: true,
        );
    }

    public static function ready(
        GatewayRouteKey $routeKey,
        string $routeLabel,
        string $gatewayName,
        string $linkedAccountLabel,
        ?string $customerPhone,
        float $amount,
    ): self {
        return new self(
            applicable: true,
            ready: true,
            routeKey: $routeKey,
            routeLabel: $routeLabel,
            gatewayName: $gatewayName,
            linkedAccountLabel: $linkedAccountLabel,
            customerPhone: $customerPhone,
            amount: $amount,
            statusLabel: 'Ready',
            statusType: 'ready',
            reason: null,
            fallbackToManual: true,
        );
    }

    public static function notReady(
        GatewayRouteKey $routeKey,
        string $routeLabel,
        ?string $gatewayName,
        ?string $linkedAccountLabel,
        ?string $customerPhone,
        ?float $amount,
        string $reason,
        bool $fallbackToManual,
    ): self {
        return new self(
            applicable: true,
            ready: false,
            routeKey: $routeKey,
            routeLabel: $routeLabel,
            gatewayName: $gatewayName,
            linkedAccountLabel: $linkedAccountLabel,
            customerPhone: $customerPhone,
            amount: $amount,
            statusLabel: 'Unavailable',
            statusType: 'not_ready',
            reason: $reason,
            fallbackToManual: $fallbackToManual,
        );
    }

    public function channelDropdownLabel(): string
    {
        return match ($this->statusType) {
            'ready' => 'Gateway ready',
            'not_ready' => 'Gateway unavailable',
            'manual' => 'Manual only',
            default => 'Unsupported',
        };
    }

    public function requiresPhoneField(): bool
    {
        return $this->applicable && $this->routeKey === GatewayRouteKey::WalletCollection;
    }

    public function showsManualSourceFields(): bool
    {
        return ! $this->ready;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applicable' => $this->applicable,
            'ready' => $this->ready,
            'route_key' => $this->routeKey?->value,
            'route_label' => $this->routeLabel,
            'gateway_name' => $this->gatewayName,
            'linked_account_label' => $this->linkedAccountLabel,
            'customer_phone' => $this->customerPhone,
            'amount' => $this->amount,
            'status_label' => $this->statusLabel,
            'status_type' => $this->statusType,
            'reason' => $this->reason,
            'fallback_to_manual' => $this->fallbackToManual,
            'channel_dropdown_label' => $this->channelDropdownLabel(),
            'requires_phone_field' => $this->requiresPhoneField(),
            'shows_manual_source_fields' => $this->showsManualSourceFields(),
        ];
    }
}
