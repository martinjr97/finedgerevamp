<?php

namespace App\PaymentPlatform\DTOs;

class CollectMoneyRequest
{
    public function __construct(
        public readonly string $internalReference,
        public readonly string $paymentMethod,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $customerPhone = null,
        public readonly ?string $providerReference = null,
        public readonly array $metadata = [],
    ) {}
}
