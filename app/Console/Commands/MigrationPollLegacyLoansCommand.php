<?php

namespace App\Console\Commands;

use App\Migration\ParallelRun\LegacyLoanPollService;
use Illuminate\Console\Command;

class MigrationPollLegacyLoansCommand extends Command
{
    protected $signature = 'migration:poll-legacy-loans';

    protected $description = 'Poll legacy DB for new active loans and stage them in migration_loan_inbox (temporary parallel-run)';

    public function handle(LegacyLoanPollService $poller): int
    {
        if (! config('legacy-parallel-run.loan_polling_enabled')) {
            $this->warn('Legacy loan polling is disabled (LEGACY_LOAN_POLLING_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $poller->poll();
        $this->info('Legacy loan poll complete.');
        $this->line('  Detected: '.$stats['detected']);
        $this->line('  Skipped (already mapped): '.$stats['skipped_mapped']);

        return self::SUCCESS;
    }
}
