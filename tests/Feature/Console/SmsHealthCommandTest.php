<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_health_reports_configuration_safely(): void
    {
        config([
            'sms.enabled' => false,
            'sms.provider' => 'log',
        ]);

        $this->artisan('sms:health')
            ->expectsOutputToContain('FineEdge SMS Gateway Health Check')
            ->expectsOutputToContain('Provider')
            ->doesntExpectOutputToContain('ZAMTEL_SMS_API_KEY')
            ->assertExitCode(0);
    }
}
