<?php

namespace App\PaymentPlatform\Providers\Kazang;

use App\Models\Customer;
use App\Models\Repayment;

final class KazangPayloadFactory
{
    public function __construct(
        private readonly KazangLegacyContextResolver $legacyContextResolver,
    ) {}

    /**
     * @return array{
     *     repayment_amount: int,
     *     client_id: int,
     *     phone_number: string,
     *     user_id: int,
     *     repayment_id: int
     * }
     */
    public function buildCollectionPayload(Repayment $repayment, ?string $phoneNumber = null): array
    {
        $repayment->loadMissing('customer');

        /** @var Customer|null $customer */
        $customer = $repayment->customer;

        if (! $customer) {
            throw new KazangException('Repayment customer is required for Kazang collection.');
        }

        $legacyContext = $this->legacyContextResolver->resolveForCustomer($customer);
        $phone = trim((string) ($phoneNumber ?? $repayment->phone_number));

        if ($phone === '') {
            throw new KazangException('A customer phone number is required for Kazang collection.');
        }

        return [
            'repayment_amount' => (int) round((float) $repayment->total_amount),
            'client_id' => $legacyContext['client_id'],
            'phone_number' => $phone,
            'user_id' => $legacyContext['user_id'],
            'repayment_id' => (int) $repayment->id,
        ];
    }
}
