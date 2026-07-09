<?php

namespace App\Services\Repayments\DTOs;

use App\Services\Repayments\Enums\RepaymentGatewayCollectionStatus;

readonly class RepaymentGatewayCollectionResult
{
    /**
     * @param  array<string, mixed>  $gatewayMetadata
     */
    public function __construct(
        public RepaymentGatewayCollectionStatus $status,
        public string $message,
        public array $gatewayMetadata = [],
        public ?string $reference = null,
        public ?string $transactionId = null,
    ) {}

    public static function initiated(string $gatewayName, array $gatewayMetadata, ?string $reference, ?string $transactionId): self
    {
        return new self(
            status: RepaymentGatewayCollectionStatus::Initiated,
            message: 'Repayment collection request has been initiated via '.$gatewayName.'. The customer should approve the payment prompt.',
            gatewayMetadata: $gatewayMetadata,
            reference: $reference,
            transactionId: $transactionId,
        );
    }

    public static function manualPending(string $message = 'Repayment recorded and is awaiting manual approval.'): self
    {
        return new self(
            status: RepaymentGatewayCollectionStatus::ManualPending,
            message: $message,
        );
    }

    public static function fallbackManual(string $reason): self
    {
        return new self(
            status: RepaymentGatewayCollectionStatus::FallbackManual,
            message: 'Gateway collection could not be initiated: '.$reason.' The repayment has been created and is awaiting manual processing.',
        );
    }

    public static function failed(string $message): self
    {
        return new self(
            status: RepaymentGatewayCollectionStatus::Failed,
            message: $message,
        );
    }

    public function flashSessionKey(): string
    {
        return $this->status->usesWarningFlash() ? 'warning' : 'status';
    }
}
