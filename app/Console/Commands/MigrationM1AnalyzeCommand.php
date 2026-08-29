<?php

namespace App\Console\Commands;

use App\Migration\ActivePortfolioAnalyzer;
use App\Migration\LegacyConnection;
use App\Migration\M1PilotImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrationM1AnalyzeCommand extends Command
{
    protected $signature = 'migration:m1-analyze {--pilot : Analyze pilot loan scope only} {--output= : Write JSON output path}';

    protected $description = 'Analyze active legacy portfolio for M1 migration (read-only on legacy DB)';

    public function handle(ActivePortfolioAnalyzer $analyzer): int
    {
        LegacyConnection::configureFromLegacyEnvFile();

        try {
            LegacyConnection::connection()->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error('Cannot connect to legacy database: '.$e->getMessage());

            return self::FAILURE;
        }

        $pilot = $this->option('pilot');
        $loanIds = $pilot ? M1PilotImporter::PILOT_LOAN_IDS : null;
        $result = $analyzer->analyze($loanIds);

        $output = $this->option('output')
            ?: base_path('docs/data-migration/tools/m1-analyze-output.json');

        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('M1 analysis complete.');
        $this->line('Active customers: '.$result['active_customer_count']);
        $this->line('Active loans: '.$result['active_loan_count']);
        $this->line('Output: '.$output);

        return self::SUCCESS;
    }
}
