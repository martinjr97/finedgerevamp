<?php

namespace App\Console\Commands;

use App\Migration\ParallelRun\LegacyRepaymentPollService;
use App\Migration\ParallelRun\LegacyRepaymentSyncService;
use Illuminate\Console\Command;

class MigrationPollLegacyRepaymentsCommand extends Command
{
    protected $signature = 'migration:poll-legacy-repayments {--no-sync : Poll only; do not promote inbox rows}';

    protected $description = 'Poll legacy DB for repayments on mapped customers and stage in migration_repayment_inbox';

    public function handle(LegacyRepaymentPollService $poller, LegacyRepaymentSyncService $sync): int
    {
        if (! config('legacy-parallel-run.repayment_polling_enabled')) {
            $this->warn('Legacy repayment polling is disabled (LEGACY_REPAYMENT_POLLING_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $poller->poll();
        $this->info('Legacy repayment poll complete.');
        $this->line('  Detected: '.$stats['detected']);
        $this->line('  Skipped (already mapped): '.$stats['skipped_mapped']);

        if (! $this->option('no-sync')) {
            $syncStats = $sync->syncPending();
            $this->info('Legacy repayment sync complete.');
            foreach ($syncStats as $key => $value) {
                $this->line('  '.ucfirst(str_replace('_', ' ', $key)).": {$value}");
            }
        }

        return self::SUCCESS;
    }
}
