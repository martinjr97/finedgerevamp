<?php

namespace Tests\Unit\Migration;

use App\Migration\Dashboard\MigrationDashboardSupport;
use Tests\TestCase;

class MigrationCustomerExceptionMetaTest extends TestCase
{
    public function test_email_exception_has_actionable_guidance(): void
    {
        $meta = MigrationDashboardSupport::customerExceptionMeta('email');

        $this->assertSame('Possible duplicate — email address', $meta['title']);
        $this->assertStringContainsString('email', strtolower($meta['description']));
        $this->assertStringContainsString('different people', strtolower($meta['guidance']));
    }

    public function test_unknown_exception_falls_back_gracefully(): void
    {
        $meta = MigrationDashboardSupport::customerExceptionMeta('custom_rule');

        $this->assertStringContainsString('custom_rule', $meta['title']);
    }
}
