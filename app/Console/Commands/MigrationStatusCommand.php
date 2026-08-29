<?php

namespace App\Console\Commands;

use App\Migration\Phases\MigrationStatusService;
use Illuminate\Console\Command;

class MigrationStatusCommand extends Command
{
    protected $signature = 'migration:status';

    protected $description = 'Show phased migration progress dashboard';

    public function handle(MigrationStatusService $status): int
    {
        $report = $status->report();

        $this->info('Migration status');

        $this->line('REFERENCE DATA');
        foreach ($report['reference_data'] as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->line('CUSTOMERS');
        foreach ($report['customers'] as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->line('ACTIVE LOANS');
        foreach ($report['active_loans'] as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->line('REPAYMENTS');
        $this->line('  mapped: '.$report['repayments']['mapped']);
        if ($report['repayments']['attribution']) {
            $this->line('  attribution: '.json_encode($report['repayments']['attribution']));
        }

        $this->line('FINANCIAL');
        $this->line('  target_outstanding: '.$report['financial']['target_outstanding']);
        $this->line('  reconciliation: '.json_encode($report['financial']['reconciliation']));

        $this->line('MIGRATION RUNS (latest)');
        foreach ($report['latest_runs'] as $run) {
            $this->line("  {$run->run_uuid} | {$run->phase} | {$run->status} | {$run->completed_at}");
        }

        return self::SUCCESS;
    }
}
