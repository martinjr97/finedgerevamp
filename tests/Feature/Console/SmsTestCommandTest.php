<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_test_works_with_log_provider(): void
    {
        config([
            'sms.enabled' => true,
            'sms.provider' => 'log',
        ]);
        Http::fake();

        $this->artisan('sms:test', [
            '--to' => '0977000001',
            '--message' => 'Test message',
        ])
            ->expectsOutputToContain('SMS Test Result')
            ->expectsOutputToContain('log')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_sms_test_does_not_send_when_disabled_unless_force(): void
    {
        config([
            'sms.enabled' => false,
            'sms.provider' => 'zamtel',
        ]);
        Http::fake();

        $this->artisan('sms:test', [
            '--to' => '0977000001',
            '--message' => 'Test message',
        ])
            ->expectsOutputToContain('SKIPPED (disabled)')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_sms_test_force_sends_when_disabled(): void
    {
        config([
            'sms.enabled' => false,
            'sms.provider' => 'log',
        ]);
        Http::fake();

        $this->artisan('sms:test', [
            '--to' => '0977000001',
            '--message' => 'Test message',
            '--force' => true,
        ])
            ->expectsOutputToContain('SMS Test Result')
            ->assertExitCode(0);
    }
}
