<?php

namespace Tests\Feature\Sms;

use App\Models\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Jobs\SendSmsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendSmsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_tags_include_sms_provider_and_category(): void
    {
        config(['sms.enabled' => true, 'sms.provider' => 'log']);

        $record = SmsMessage::create([
            'phone_number' => '260977000001',
            'normalized_phone' => '[260977000001]',
            'message_category' => SmsCategory::Otp,
            'message_type' => 'password_reset_otp',
            'message_body' => '[REDACTED OTP MESSAGE]',
            'message_preview' => '[REDACTED OTP MESSAGE]',
            'message_length' => 20,
            'provider' => 'log',
            'status' => 'queued',
        ]);

        $job = new SendSmsJob($record->id, 'Your OTP is 123456');
        $tags = $job->tags();

        $this->assertContains('sms', $tags);
        $this->assertContains('sms:'.$record->id, $tags);
        $this->assertContains('provider:log', $tags);
        $this->assertContains('category:otp', $tags);
        $this->assertTrue(collect($tags)->contains(fn (string $tag) => str_starts_with($tag, 'recipient:')));
    }

    public function test_job_marks_record_sent_with_log_provider(): void
    {
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
        Http::fake();

        $record = SmsMessage::create([
            'phone_number' => '260977000001',
            'normalized_phone' => '[260977000001]',
            'message_category' => SmsCategory::General,
            'message_type' => 'test',
            'message_body' => 'Hello',
            'message_preview' => 'Hello',
            'message_length' => 5,
            'provider' => 'log',
            'status' => 'queued',
        ]);

        SendSmsJob::dispatchSync($record->id, 'Hello');

        $record->refresh();
        $this->assertSame('sent', $record->status);
        $this->assertNotNull($record->sent_at);
    }

    public function test_job_skips_when_sms_disabled(): void
    {
        config(['sms.enabled' => false, 'sms.provider' => 'zamtel']);
        Http::fake();

        $record = SmsMessage::create([
            'phone_number' => '260977000001',
            'normalized_phone' => '[260977000001]',
            'message_category' => SmsCategory::General,
            'message_type' => 'test',
            'message_body' => 'Hello',
            'message_preview' => 'Hello',
            'message_length' => 5,
            'provider' => 'zamtel',
            'status' => 'queued',
        ]);

        SendSmsJob::dispatchSync($record->id, 'Hello');

        $record->refresh();
        $this->assertSame('skipped', $record->status);
        $this->assertSame('disabled', $record->skip_reason);
        Http::assertNothingSent();
    }
}
