<?php

namespace App\Console\Commands;

use App\Migration\ParallelRun\LegacyExpensePollService;
use App\Migration\ParallelRun\LegacyExpenseSyncService;
use Illuminate\Console\Command;

class MigrationPollLegacyExpensesCommand extends Command
{
    protected $signature = 'migration:poll-legacy-expenses {--no-sync : Poll only; do not promote inbox rows}';

    protected $description = 'Poll legacy DB for new expenses during parallel run and sync treasury balances';

    public function handle(LegacyExpensePollService $poller, LegacyExpenseSyncService $sync): int
    {
        if (! config('legacy-parallel-run.expense_polling_enabled')) {
            $this->warn('Legacy expense polling is disabled (LEGACY_EXPENSE_POLLING_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $poller->poll();
        $this->info('Legacy expense poll complete.');
        foreach ($stats as $key => $value) {
            $this->line('  '.ucfirst(str_replace('_', ' ', $key)).": {$value}");
        }

        if (! $this->option('no-sync')) {
            $syncStats = $sync->syncPending();
            $this->info('Legacy expense sync complete.');
            foreach ($syncStats as $key => $value) {
                $this->line('  '.ucfirst(str_replace('_', ' ', $key)).": {$value}");
            }
        }

        return self::SUCCESS;
    }
}
