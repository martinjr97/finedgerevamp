<?php

namespace App\Console\Commands;

use App\Migration\Phases\MigratedPortfolioCorrector;
use Illuminate\Support\Facades\File;

class MigrationCorrectPortfolioCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:correct-portfolio
        {--dry-run : Non-destructive preview (default)}
        {--promote : Apply corrections to migrated portfolio data}
        {--only= : Comma-separated: disbursement,repayment_splits,payment_schedules,loan_ledgers}
        {--run= : Migration run UUID}';

    protected $description = 'Post-migration corrections — disbursement, splits, schedules, loan ledgers';

    public function handle(MigratedPortfolioCorrector $corrector): int
    {
        $this->guardPromoteExplicit();

        $only = collect(explode(',', (string) $this->option('only')))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->all();

        $disbursement = $only === [] || in_array('disbursement', $only, true);
        $repaymentSplits = $only === [] || in_array('repayment_splits', $only, true);
        $paymentSchedules = $only === [] || in_array('payment_schedules', $only, true);
        $loanLedgers = $only === [] || in_array('loan_ledgers', $only, true);

        $summary = $corrector->run(
            promote: $this->isPromote(),
            disbursement: $disbursement,
            repaymentSplits: $repaymentSplits,
            paymentSchedules: $paymentSchedules,
            loanLedgers: $loanLedgers,
            runUuid: $this->runUuid(),
        );

        $path = base_path('docs/data-migration/tools/m2-portfolio-correction.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->outputSummary($summary);
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}
