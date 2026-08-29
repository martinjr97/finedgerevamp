<?php

namespace App\Console\Commands;

use App\Migration\M1PilotImporter;
use App\Migration\Replay\LegacyRepaymentReplayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrationM1ReplayCommand extends Command
{
    protected $signature = 'migration:m1-replay
        {--dry-run : Non-destructive replay into staging tables (default)}
        {--product= : Filter reconciliation report by product class}
        {--pilot : Limit to M1 pilot active loan IDs}
        {--output= : JSON summary output path}';

    protected $description = 'Replay legacy repayments for active loans (M1.1 dry-run by default)';

    public function handle(LegacyRepaymentReplayService $replayService): int
    {
        $loanIds = $this->option('pilot') ? M1PilotImporter::PILOT_ACTIVE_LOAN_IDS : null;
        $product = $this->option('product');

        $summary = $replayService->dryRun($loanIds, $product ?: null);

        $output = $this->option('output')
            ?: base_path('docs/data-migration/tools/m1-replay-output.json');

        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('M1 replay dry-run complete. Run #'.$summary['migration_run_id']);
        $this->line(json_encode($summary['attribution'] ?? [], JSON_PRETTY_PRINT));
        $this->line('Loan status: '.json_encode($summary['loan_status'] ?? []));
        $this->line('Output: '.$output);

        return self::SUCCESS;
    }
}
