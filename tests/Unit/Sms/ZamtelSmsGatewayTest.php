<?php

namespace Tests\Unit\Sms;

use App\Sms\DTOs\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Providers\ZamtelSmsGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZamtelSmsGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sms.zamtel.api_key' => 'secret-api-key-123',
            'sms.zamtel.sender_id' => 'FineEdge',
            'sms.zamtel.base_url' => 'https://bulksms.zamtel.co.zm',
        ]);
    }

    public function test_builds_correct_get_url(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'OK'], 200),
        ]);

        app(ZamtelSmsGateway::class)->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        Http::assertSentCount(1);

        $recorded = Http::recorded();
        $url = $recorded[0][0]->url();

        $this->assertSame('GET', $recorded[0][0]->method());
        $this->assertStringContainsString('bulksms.zamtel.co.zm', $url);
        $this->assertStringContainsString('/api/v2.1/action/send/api_key/', $url);
        $this->assertStringContainsString('/contacts/', $url);
        $this->assertStringContainsString('260977000001', $url);
        $this->assertStringContainsString('/senderId/', $url);
        $this->assertStringContainsString('/message/', $url);
        $this->assertStringContainsString('/message/Hello', $url);
    }

    public function test_http_202_with_success_true_is_accepted(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'Accepted', 'reference' => 'ref-1'], 202),
        ]);

        $result = app(ZamtelSmsGateway::class)->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $this->assertTrue($result->success());
        $this->assertSame(202, $result->httpStatus);
        $this->assertSame('ref-1', $result->providerReference);
    }

    public function test_http_200_with_success_true_is_accepted(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'OK'], 200),
        ]);

        $result = app(ZamtelSmsGateway::class)->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $this->assertTrue($result->success());
        $this->assertSame(200, $result->httpStatus);
    }

    public function test_provider_rejection_returns_failed_result(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Rejected'], 202),
        ]);

        $result = app(ZamtelSmsGateway::class)->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $this->assertTrue($result->failed());
        $this->assertFalse($result->shouldRetry());
    }

    public function test_server_errors_are_retryable(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false], 500),
        ]);

        $result = app(ZamtelSmsGateway::class)->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $this->assertTrue($result->failed());
        $this->assertTrue($result->shouldRetry());
    }

    public function test_api_key_is_redacted_from_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'secret-api-key-123 accepted',
            ], 202),
        ]);

        $result = app(ZamtelSmsGateway::class)->send(new SmsMessage(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $encoded = json_encode($result->rawResponse) ?: '';
        $this->assertStringNotContainsString('secret-api-key-123', $encoded);
    }
}
