<?php

namespace Tests\Unit\Sms;

use App\Sms\DTOs\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Providers\LogSmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSmsGatewayTest extends TestCase
{
    public function test_log_gateway_does_not_call_http(): void
    {
        Http::fake();
        Log::spy();

        $gateway = app(LogSmsGateway::class);
        $result = $gateway->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello world',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        Http::assertNothingSent();
        $this->assertTrue($result->success());
        $this->assertSame('log', $result->provider);
        Log::shouldHaveReceived('info')->once();
    }
}
