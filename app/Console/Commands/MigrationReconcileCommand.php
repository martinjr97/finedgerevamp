<?php

namespace App\Console\Commands;

use App\Migration\Phases\MigrationReconciliationReader;

class MigrationReconcileCommand extends MigrationPhaseCommand
{
    protected $signature = 'migration:reconcile
        {--customer= : Legacy user id}
        {--loan= : Legacy loan id}';

    protected $description = 'Phase 5 — read-only reconciliation of migrated active loans';

    public function handle(MigrationReconciliationReader $reader): int
    {
        $summary = $reader->reconcile(
            legacyLoanId: $this->option('loan') ? (int) $this->option('loan') : null,
            legacyUserId: $this->option('customer') ? (int) $this->option('customer') : null,
        );

        $this->info('Read-only reconciliation');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
