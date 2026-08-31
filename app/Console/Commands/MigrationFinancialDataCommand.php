<?php

namespace App\Console\Commands;

use App\Migration\Phases\FinancialDataMigrator;
use Illuminate\Support\Facades\File;

class MigrationFinancialDataCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:financial-data
        {--dry-run : Non-destructive preview (default when --promote omitted)}
        {--promote : Write categories and financial transactions to the target database}
        {--only= : categories|expense_categories|income_categories|creditors|assets|expenses|incomes|creditor_conversions}
        {--from-date= : Import expenses/incomes on or after this date (YYYY-MM-DD). Defaults to MIGRATION_FINANCIAL_FROM_DATE or today.}
        {--run= : Migration run UUID}';

    protected $description = 'Migrate legacy financial categories, expenses, and manual incomes into financial_transactions';

    public function handle(FinancialDataMigrator $migrator): int
    {
        $this->guardPromoteExplicit();

        $summary = $migrator->run(
            promote: $this->isPromote(),
            only: $this->option('only') ?: null,
            fromDate: $this->option('from-date') ?: null,
            runUuid: $this->runUuid(),
        );

        $path = base_path('docs/data-migration/tools/m2-financial-dry-run.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->outputSummary($summary);
        $this->line("Output: {$path}");
        $this->line('Recommended order: migration:financial-data --only=categories --promote, then --only=creditors --promote, then --only=assets --promote, then --only=expenses --from-date=YYYY-MM-DD --promote');

        return self::SUCCESS;
    }
}
