<?php

namespace App\PaymentPlatform\DTOs;

class DisburseMoneyRequest
{
    public function __construct(
        public readonly string $internalReference,
        public readonly string $paymentMethod,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $customerAccount,
        public readonly string $issuerName,
        public readonly ?string $providerReference = null,
        public readonly array $metadata = [],
    ) {}
}
