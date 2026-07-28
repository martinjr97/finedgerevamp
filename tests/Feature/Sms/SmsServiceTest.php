<?php

namespace Tests\Feature\Sms;

use App\Models\SmsMessage;
use App\Sms\DTOs\SmsMessage as SmsMessageDto;
use App\Sms\Enums\SmsCategory;
use App\Sms\Jobs\SendSmsJob;
use App\Sms\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_disabled_skips_without_dispatching_or_logging(): void
    {
        config(['sms.enabled' => false, 'sms.provider' => 'zamtel']);
        Queue::fake();
        Http::fake();
        Log::spy();

        $record = app(SmsService::class)->queueSend([
            'phone' => '0977000001',
            'body' => 'Test message',
            'category' => SmsCategory::General,
            'message_type' => 'test',
        ]);

        $this->assertNotNull($record);
        $this->assertSame('skipped', $record->status);
        $this->assertSame('disabled', $record->skip_reason);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('info');
    }

    public function test_max_length_exceeded_marks_skipped(): void
    {
        config(['sms.enabled' => true, 'sms.max_length' => 10]);
        Queue::fake();

        $record = app(SmsService::class)->queueSend([
            'phone' => '0977000001',
            'body' => 'This message is definitely too long',
            'category' => SmsCategory::General,
            'message_type' => 'test',
        ]);

        $this->assertSame('skipped', $record->status);
        $this->assertSame('too_long', $record->skip_reason);
        Queue::assertNothingPushed();
    }

    public function test_otp_message_body_is_redacted_in_database(): void
    {
        config(['sms.enabled' => false]);

        $record = app(SmsService::class)->queueSend([
            'phone' => '0977000001',
            'body' => 'Your OTP is 123456',
            'category' => SmsCategory::Otp,
            'message_type' => 'password_reset_otp',
        ]);

        $this->assertSame('skipped', $record->status);
        $this->assertSame('disabled', $record->skip_reason);
        $this->assertSame('[REDACTED OTP MESSAGE]', $record->message_body);
        $this->assertSame('[REDACTED OTP MESSAGE]', $record->message_preview);
    }

    public function test_send_now_when_disabled_skips_without_logging(): void
    {
        config(['sms.enabled' => false, 'sms.provider' => 'log']);
        Http::fake();
        Log::spy();

        $result = app(SmsService::class)->sendNow(new SmsMessageDto(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $this->assertTrue($result->skipped());
        $this->assertFalse($result->success());
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('info');
    }

    public function test_enabled_with_log_provider_dispatches_job(): void
    {
        config([
            'sms.enabled' => true,
            'sms.provider' => 'log',
            'sms.queues.sms' => 'notifications',
        ]);
        Queue::fake();

        $record = app(SmsService::class)->queueSend([
            'phone' => '0977000001',
            'body' => 'Hello',
            'category' => SmsCategory::General,
            'message_type' => 'test',
        ]);

        $this->assertSame('queued', $record->status);
        $this->assertSame('log', $record->provider);
        Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) {
            return $job->queue === 'notifications';
        });
    }

    public function test_send_recorded_marks_sent_with_log_provider(): void
    {
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
        Http::fake();

        $record = app(SmsService::class)->sendRecorded([
            'phone' => '0977000001',
            'body' => 'Hello sync',
            'category' => SmsCategory::General,
            'message_type' => 'admin_communication',
        ]);

        $this->assertNotNull($record);
        $this->assertSame('sent', $record->status);
        $this->assertSame('log', $record->provider);
        $this->assertNotNull($record->sent_at);
        Http::assertNothingSent();
    }

    public function test_send_now_with_log_provider_when_enabled(): void
    {
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
        Http::fake();
        Log::spy();

        $result = app(SmsService::class)->sendNow(new SmsMessageDto(
            phone: '0977000001',
            body: 'Hello',
            category: SmsCategory::General,
            messageType: 'test',
        ));

        $this->assertTrue($result->success());
        $this->assertSame('log', $result->provider);
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->withArgs(function ($message) {
            return is_string($message) && str_contains($message, 'SMS (log provider)');
        });
    }
}
