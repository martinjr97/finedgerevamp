<?php

namespace App\Console\Commands;

use App\Migration\Phases\RepaymentMigrator;
use Illuminate\Support\Facades\File;

class MigrationRepaymentsCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:repayments
        {--dry-run : Non-destructive preview (default)}
        {--promote : Promote A_DIRECT and B_RECONSTRUCTED repayments}
        {--customer= : Legacy user id filter}
        {--run= : Migration run UUID}';

    protected $description = 'Phase 4 — migrate repayments for active loan portfolio';

    public function handle(RepaymentMigrator $migrator): int
    {
        $this->guardPromoteExplicit();

        try {
            $summary = $migrator->run(
                promote: $this->isPromote(),
                legacyUserId: $this->option('customer') ? (int) $this->option('customer') : null,
                runUuid: $this->runUuid(),
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = base_path('docs/data-migration/tools/m2-repayments-dry-run.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->outputSummary($summary);
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}
