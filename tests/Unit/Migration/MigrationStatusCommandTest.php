<?php

namespace Tests\Unit\Migration;

use App\Migration\Phases\MigrationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MigrationStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_command_handles_nested_report_sections(): void
    {
        $this->mock(MigrationStatusService::class, function ($mock): void {
            $mock->shouldReceive('report')->once()->andReturn([
                'reference_data' => [
                    'products' => '0/4 mapped',
                    'reference_manual_review' => [
                        'branches' => 0,
                        'banks' => 1,
                    ],
                ],
                'customers' => ['mapped_legacy_identities' => 0],
                'active_loans' => ['migrated' => 0],
                'repayments' => ['mapped' => 0, 'attribution' => null],
                'financial' => [
                    'target_outstanding' => 0,
                    'reconciliation' => ['PASS' => null, 'FAIL' => null, 'MANUAL_REVIEW' => null],
                ],
                'latest_runs' => collect(),
            ]);
        });

        $this->artisan('migration:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('reference_manual_review:')
            ->expectsOutputToContain('banks: 1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
