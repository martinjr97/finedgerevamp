<?php

namespace App\Console\Commands;

use App\Migration\ParallelRun\LegacyCustomerPollService;
use App\Migration\ParallelRun\LegacyCustomerSyncService;
use Illuminate\Console\Command;

class MigrationPollLegacyCustomersCommand extends Command
{
    protected $signature = 'migration:poll-legacy-customers {--no-sync : Poll only; do not promote inbox rows}';

    protected $description = 'Poll legacy for unmapped borrowers (parallel-run) and optionally promote customers';

    public function handle(LegacyCustomerPollService $poller, LegacyCustomerSyncService $sync): int
    {
        if (! config('legacy-parallel-run.customer_polling_enabled')) {
            $this->warn('Legacy customer polling is disabled (LEGACY_CUSTOMER_POLLING_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $poller->poll();
        $this->info('Legacy customer poll complete.');
        $this->line('  Detected: '.$stats['detected']);
        $this->line('  Skipped (already mapped): '.$stats['skipped_mapped']);
        $this->line('  Skipped (no legacy customer row): '.$stats['skipped_no_customer_row']);

        if (! $this->option('no-sync')) {
            $syncStats = $sync->syncPending();
            $this->info('Legacy customer sync complete.');
            foreach ($syncStats as $key => $value) {
                $this->line('  '.ucfirst(str_replace('_', ' ', $key)).": {$value}");
            }
        }

        return self::SUCCESS;
    }
}
