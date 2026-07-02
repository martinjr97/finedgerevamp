<?php

namespace App\Services\Loans\DTOs;

readonly class LoanApprovalAutoDisbursementPreview
{
    public function __construct(
        public bool $autoDisbursementApplicable,
        public bool $autoDisbursementReady,
        public ?string $routeLabel = null,
        public ?string $gatewayName = null,
        public ?string $linkedAccountLabel = null,
        public ?float $linkedAccountBalance = null,
        public ?float $disbursementAmount = null,
        public ?string $destinationLabel = null,
        public ?string $warningMessage = null,
        public ?string $statusLabel = null,
        public string $statusType = 'manual',
        public ?string $balanceWarning = null,
    ) {}

    public static function manualOnly(float $disbursementAmount, ?string $destinationLabel = null): self
    {
        return new self(
            autoDisbursementApplicable: false,
            autoDisbursementReady: false,
            disbursementAmount: $disbursementAmount,
            destinationLabel: $destinationLabel,
            statusType: 'manual',
        );
    }

    public static function ready(
        string $routeLabel,
        string $gatewayName,
        string $linkedAccountLabel,
        ?float $linkedAccountBalance,
        float $disbursementAmount,
        string $destinationLabel,
        string $warningMessage,
        ?string $balanceWarning = null,
    ): self {
        return new self(
            autoDisbursementApplicable: true,
            autoDisbursementReady: true,
            routeLabel: $routeLabel,
            gatewayName: $gatewayName,
            linkedAccountLabel: $linkedAccountLabel,
            linkedAccountBalance: $linkedAccountBalance,
            disbursementAmount: $disbursementAmount,
            destinationLabel: $destinationLabel,
            warningMessage: $warningMessage,
            statusLabel: 'Ready',
            statusType: 'ready',
            balanceWarning: $balanceWarning,
        );
    }

    public static function configuredNotReady(
        string $routeLabel,
        ?string $gatewayName,
        ?string $linkedAccountLabel,
        ?float $linkedAccountBalance,
        float $disbursementAmount,
        string $destinationLabel,
        string $failureReason,
        string $statusLabel,
    ): self {
        return new self(
            autoDisbursementApplicable: true,
            autoDisbursementReady: false,
            routeLabel: $routeLabel,
            gatewayName: $gatewayName,
            linkedAccountLabel: $linkedAccountLabel,
            linkedAccountBalance: $linkedAccountBalance,
            disbursementAmount: $disbursementAmount,
            destinationLabel: $destinationLabel,
            warningMessage: $failureReason,
            statusLabel: $statusLabel,
            statusType: 'not_ready',
        );
    }

    public function formattedBalance(): ?string
    {
        if ($this->linkedAccountBalance === null) {
            return null;
        }

        return 'ZMW '.number_format($this->linkedAccountBalance, 2);
    }

    public function formattedDisbursementAmount(): ?string
    {
        if ($this->disbursementAmount === null) {
            return null;
        }

        return 'ZMW '.number_format($this->disbursementAmount, 2);
    }
}
