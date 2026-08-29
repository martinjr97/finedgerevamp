<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationM1ReconcileCommand extends Command
{
    protected $signature = 'migration:m1-reconcile
        {--run= : Migration run ID (defaults to latest m1.1 replay run)}';

    protected $description = 'Print M1.1 replay reconciliation summary from staging tables';

    public function handle(): int
    {
        $runId = $this->option('run') ?: DB::table('migration_runs')
            ->where('phase', 'm1.1')
            ->orderByDesc('id')
            ->value('id');

        if (! $runId) {
            $this->error('No M1.1 replay run found. Run migration:m1-replay --dry-run first.');

            return self::FAILURE;
        }

        $run = DB::table('migration_runs')->where('id', $runId)->first();
        $summary = json_decode($run->summary ?? '{}', true);

        $this->info("M1.1 reconciliation — run #{$runId} ({$run->name})");
        $this->line('Attribution: '.json_encode($summary['attribution'] ?? []));
        $this->line('Loan status: '.json_encode($summary['loan_status'] ?? []));
        $this->line('Conservation: '.json_encode($summary['conservation'] ?? []));
        $this->line('Product stats: '.json_encode($summary['product_stats'] ?? [], JSON_PRETTY_PRINT));
        $this->line('Promotable loans: '.($summary['promotable_loans'] ?? 0));
        $this->line('Manual/blocked: '.($summary['manual_or_blocked'] ?? 0));

        if (! empty($summary['ambiguous_cases'])) {
            $this->warn('C_AMBIGUOUS cases: '.count($summary['ambiguous_cases']));
        }

        return self::SUCCESS;
    }
}
