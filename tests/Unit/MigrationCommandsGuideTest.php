<?php

namespace Tests\Unit;

use App\Migration\Dashboard\MigrationCommandsGuide;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationCommandsGuideTest extends TestCase
{
    #[Test]
    public function phases_are_ordered_and_include_core_portfolio_steps(): void
    {
        $phases = MigrationCommandsGuide::phases();

        $this->assertNotEmpty($phases);
        $this->assertStringContainsString('REFERENCE DATA', MigrationCommandsGuide::executionOrder());

        $titles = array_column($phases, 'title');
        $this->assertContains('Reference data', $titles);
        $this->assertContains('Customers', $titles);
        $this->assertContains('Active loans', $titles);
        $this->assertContains('Repayments', $titles);
        $this->assertContains('Reconciliation', $titles);

        foreach ($phases as $phase) {
            $this->assertNotEmpty($phase['steps']);
            foreach ($phase['steps'] as $step) {
                $this->assertStringStartsWith('php artisan migration:', $step['command']);
            }
        }
    }
}
