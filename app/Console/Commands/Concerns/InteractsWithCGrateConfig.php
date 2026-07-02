<?php

namespace App\Console\Commands\Concerns;

trait InteractsWithCGrateConfig
{
    protected function ensureCGrateConfigured(): ?int
    {
        if (! (bool) config('cgrate.enabled')) {
            $this->error('cGrate is disabled. Set CGRATE_ENABLED=true in .env before running UAT commands.');

            return self::FAILURE;
        }

        if (trim((string) config('cgrate.base_url')) === '') {
            $this->error('CGRATE_BASE_URL is not configured.');

            return self::FAILURE;
        }

        if (trim((string) config('cgrate.username')) === '' || trim((string) config('cgrate.password')) === '') {
            $this->error('CGRATE_USERNAME and CGRATE_PASSWORD must be set.');

            return self::FAILURE;
        }

        return null;
    }
}
