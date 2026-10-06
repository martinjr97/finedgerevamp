<?php

namespace Tests\Unit\PaymentPlatform;

use App\Models\Customer;
use App\Models\Repayment;
use App\PaymentPlatform\Providers\Kazang\KazangPayloadFactory;
use Tests\TestCase;

class KazangPayloadFactoryTest extends TestCase
{
    public function test_builds_java_compatible_collection_payload(): void
    {
        $customer = new Customer([
            'metadata' => [
                'legacy_user_id' => 501,
                'legacy_client_id' => 36,
            ],
        ]);
        $customer->id = 10;

        $repayment = new Repayment([
            'total_amount' => 125.50,
            'phone_number' => '260971234567',
        ]);
        $repayment->id = 99;
        $repayment->setRelation('customer', $customer);

        $factory = app(KazangPayloadFactory::class);

        $payload = $factory->buildCollectionPayload($repayment);

        $this->assertSame([
            'repayment_amount' => 126,
            'client_id' => 36,
            'phone_number' => '260971234567',
            'user_id' => 501,
            'repayment_id' => 99,
        ], $payload);
    }
}
