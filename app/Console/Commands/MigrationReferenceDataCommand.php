<?php

namespace App\Console\Commands;

use App\Migration\Phases\ReferenceDataMigrator;
use Illuminate\Support\Facades\File;

class MigrationReferenceDataCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:reference-data
        {--dry-run : Non-destructive preview (default when --promote omitted)}
        {--promote : Write target reference data and durable mappings}
        {--only= : products|companies|banks|wallet_providers|providers|branches|relationship_managers|marketeer|customer_groups|groups}
        {--run= : Migration run UUID}';

    protected $description = 'Phase 1 — migrate reference/master data (companies, products, banks, wallet providers)';

    public function handle(ReferenceDataMigrator $migrator): int
    {
        $this->guardPromoteExplicit();

        $summary = $migrator->run(
            promote: $this->isPromote(),
            only: $this->option('only') ?: null,
            runUuid: $this->runUuid(),
        );

        $path = base_path('docs/data-migration/tools/m2-reference-dry-run.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->outputSummary($summary);
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}
