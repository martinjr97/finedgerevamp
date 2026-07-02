<?php

namespace App\PaymentPlatform\DTOs;

class GatewayStatusResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly string $normalizedStatus,
        public readonly ?string $providerTransactionId = null,
        public readonly ?int $responseCode = null,
        public readonly ?string $responseMessage = null,
        public readonly array $rawPayload = [],
    ) {}

    public function isConfirmed(): bool
    {
        return $this->normalizedStatus === 'confirmed';
    }

    public function isPending(): bool
    {
        return $this->normalizedStatus === 'pending';
    }

    public function isTerminal(): bool
    {
        return in_array($this->normalizedStatus, ['confirmed', 'failed', 'rejected', 'expired'], true);
    }
}
