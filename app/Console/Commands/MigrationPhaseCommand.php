<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class MigrationPhaseCommand extends Command
{
    protected function isPromote(): bool
    {
        return (bool) $this->option('promote');
    }

    protected function isDryRun(): bool
    {
        return ! $this->isPromote();
    }

    protected function runUuid(): ?string
    {
        $run = $this->option('run');

        return $run && $run !== 'null' ? (string) $run : null;
    }

    protected function outputSummary(array $summary): void
    {
        $this->info('Migration run UUID: '.($summary['run_uuid'] ?? 'n/a'));
        unset($summary['run_uuid']);
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function guardPromoteExplicit(): bool
    {
        if ($this->isPromote()) {
            return true;
        }

        $this->warn('Dry-run mode — no operational target data written. Use --promote to write.');

        return false;
    }
}
