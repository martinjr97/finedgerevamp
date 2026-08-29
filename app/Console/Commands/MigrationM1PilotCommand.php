<?php

namespace App\Console\Commands;

use App\Migration\M1PilotImporter;
use App\Migration\M1ReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationM1PilotCommand extends Command
{
    protected $signature = 'migration:m1-pilot {--promote : Import into live revamp tables (idempotent)} {--reconcile : Run reconciliation after import}';

    protected $description = 'Run M1 pilot migration into staging tables (optional promote to revamp models)';

    public function handle(M1PilotImporter $importer, M1ReconciliationService $reconciliation): int
    {
        $runId = DB::table('migration_runs')->insertGetId([
            'name' => 'm1-pilot-'.now()->format('Ymd-His'),
            'phase' => 'm1',
            'scope' => 'pilot-20-loans',
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $promote = (bool) $this->option('promote');
        $summary = $importer->import($runId, $promote);

        $recon = null;
        if ($this->option('reconcile') && $promote) {
            $recon = $reconciliation->reconcileRun($runId);
            $summary['reconciliation'] = $recon;
        }

        DB::table('migration_runs')->where('id', $runId)->update([
            'status' => 'completed',
            'summary' => json_encode($summary),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('M1 pilot migration run #'.$runId.' completed.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
