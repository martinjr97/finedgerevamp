<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_health_command_runs_and_reports_status(): void
    {
        $this->artisan('payments:health')
            ->expectsOutputToContain('FineEdge Payment Platform Health Check')
            ->expectsOutputToContain('Queue connection')
            ->assertExitCode(0);
    }
}
