<?php

namespace App\Console\Commands;

use App\Migration\Phases\CustomerMigrator;
use Illuminate\Support\Facades\File;

class MigrationCustomersCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:customers
        {--dry-run : Non-destructive preview (default)}
        {--promote : Create/match customers in target}
        {--correct-existing : Update already-migrated customers (product/group/company corrections)}
        {--legacy-id= : Single legacy user id}
        {--limit= : Limit number of customers}
        {--run= : Migration run UUID}';

    protected $description = 'Phase 2 — migrate ALL legacy customers';

    public function handle(CustomerMigrator $migrator): int
    {
        $this->guardPromoteExplicit();

        $correctExisting = (bool) $this->option('correct-existing');
        if ($correctExisting && ! $this->isPromote()) {
            $this->warn('Correct-existing mode requires --promote. Enabling promote.');
        }

        $summary = $migrator->run(
            promote: $this->isPromote() || $correctExisting,
            legacyUserId: $this->option('legacy-id') ? (int) $this->option('legacy-id') : null,
            limit: $this->option('limit') ? (int) $this->option('limit') : null,
            runUuid: $this->runUuid(),
            correctExisting: $correctExisting,
        );

        $path = base_path('docs/data-migration/tools/m2-customers-dry-run.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->outputSummary($summary);
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}
