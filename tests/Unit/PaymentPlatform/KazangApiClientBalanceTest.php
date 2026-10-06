<?php

namespace Tests\Unit\PaymentPlatform;

use App\PaymentPlatform\Providers\Kazang\KazangApiClient;
use App\PaymentPlatform\Providers\Kazang\KazangException;
use Mockery;
use Tests\TestCase;

class KazangApiClientBalanceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_merchant_balance_parses_auth_client_response(): void
    {
        config(['kazang.enabled' => true, 'kazang.balance_mode' => 'kwacha']);

        $client = Mockery::mock(KazangApiClient::class)->makePartial();
        $client->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'response_code' => '0',
                'response_message' => 'Successful',
                'session_uuid' => 'abc-123',
                'balance' => 12500.75,
            ]);

        $result = $client->getMerchantBalance();

        $this->assertSame(12500.75, $result['balance']);
        $this->assertSame('ZMW', $result['currency']);
        $this->assertSame('0', $result['response_code']);
    }

    public function test_get_merchant_balance_supports_minor_units_mode(): void
    {
        config(['kazang.enabled' => true, 'kazang.balance_mode' => 'minor_units']);

        $client = Mockery::mock(KazangApiClient::class)->makePartial();
        $client->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'response_code' => '0',
                'balance' => 1250075,
            ]);

        $result = $client->getMerchantBalance();

        $this->assertSame(12500.75, $result['balance']);
    }

    public function test_get_merchant_balance_throws_when_auth_fails(): void
    {
        config(['kazang.enabled' => true]);

        $client = Mockery::mock(KazangApiClient::class)->makePartial();
        $client->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'response_code' => '2',
                'response_message' => 'Invalid credentials',
            ]);

        $this->expectException(KazangException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $client->getMerchantBalance();
    }
}
