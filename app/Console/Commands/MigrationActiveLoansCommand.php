<?php

namespace App\Console\Commands;

use App\Migration\Phases\ActiveLoanMigrator;
use Illuminate\Support\Facades\File;

class MigrationActiveLoansCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:active-loans
        {--dry-run : Non-destructive preview (default)}
        {--promote : Create active loans in target}
        {--legacy-id= : Single legacy loan id}
        {--customer= : Legacy user id filter}
        {--limit= : Limit loans processed}
        {--run= : Migration run UUID}';

    protected $description = 'Phase 3 — migrate active loans (status 301)';

    public function handle(ActiveLoanMigrator $migrator): int
    {
        $this->guardPromoteExplicit();

        $summary = $migrator->run(
            promote: $this->isPromote(),
            legacyLoanId: $this->option('legacy-id') ? (int) $this->option('legacy-id') : null,
            legacyCustomerUserId: $this->option('customer') ? (int) $this->option('customer') : null,
            limit: $this->option('limit') ? (int) $this->option('limit') : null,
            runUuid: $this->runUuid(),
        );

        $path = base_path('docs/data-migration/tools/m2-active-loans-dry-run.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->outputSummary($summary);
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}
