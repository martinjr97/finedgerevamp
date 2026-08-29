<?php

namespace App\Console\Commands;

use App\Migration\Phases\MigrationRollbackService;
use Illuminate\Console\Command;

class MigrationRollbackCommand extends Command
{
    protected $signature = 'migration:rollback
        {--run= : Migration run UUID (required)}
        {--force : Allow rollback when financial activity exists}';

    protected $description = 'Rollback records created by a specific migration run';

    public function handle(MigrationRollbackService $rollback): int
    {
        $runUuid = $this->option('run');
        if (! $runUuid) {
            $this->error('--run=<uuid> is required');

            return self::FAILURE;
        }

        try {
            $summary = $rollback->rollback($runUuid, (bool) $this->option('force'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rollback complete for run {$runUuid}");
        $this->line(json_encode($summary, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
