<?php

namespace App\Console\Commands;

use App\Migration\ParallelRun\LegacyRepaymentSyncService;
use Illuminate\Console\Command;

class MigrationSyncLegacyRepaymentsCommand extends Command
{
    protected $signature = 'migration:sync-legacy-repayments {--limit= : Max inbox rows to process}';

    protected $description = 'Promote pending legacy repayments from migration_repayment_inbox into revamp';

    public function handle(LegacyRepaymentSyncService $sync): int
    {
        if (! config('legacy-parallel-run.repayment_polling_enabled')) {
            $this->warn('Legacy repayment sync is disabled (LEGACY_REPAYMENT_POLLING_ENABLED=false).');

            return self::SUCCESS;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $stats = $sync->syncPending($limit);

        $this->info('Legacy repayment sync complete.');
        foreach ($stats as $key => $value) {
            $this->line('  '.ucfirst(str_replace('_', ' ', $key)).": {$value}");
        }

        return self::SUCCESS;
    }
}
