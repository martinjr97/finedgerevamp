<?php

namespace Tests\Support;

use Database\Seeders\SmsTemplateSeeder;

trait SeedsSmsTemplates
{
    protected function seedSmsTemplates(): void
    {
        $this->seed(SmsTemplateSeeder::class);
    }
}
