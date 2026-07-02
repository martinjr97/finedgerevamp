<?php

namespace App\PaymentPlatform\DTOs;

class GatewayResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerReference = null,
        public readonly ?string $providerTransactionId = null,
        public readonly ?int $responseCode = null,
        public readonly ?string $responseMessage = null,
        public readonly string $normalizedStatus = 'pending',
        public readonly array $rawPayload = [],
    ) {}
}
