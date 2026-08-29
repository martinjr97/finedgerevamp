<?php

namespace App\Console\Commands;

use App\Migration\Phases\PrePromotionAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrationAuditCommand extends Command
{
    protected $signature = 'migration:audit
        {--output= : JSON output path}';

    protected $description = 'Pre-promotion consistency audit (read-only, no --promote)';

    public function handle(PrePromotionAuditService $audit): int
    {
        $report = $audit->run();

        $path = $this->option('output')
            ?: base_path('docs/data-migration/tools/m2-pre-promotion-audit.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Pre-promotion audit complete (read-only)');
        $this->line('Users: '.$report['user_population']['total_users']);
        $this->line('Customers: '.$report['user_population']['total_customers_rows']);
        $this->line('True migration population: '.$report['user_population']['true_migration_population']);
        $this->line('Admin/staff excluded: '.$report['user_population']['admin_or_staff_excluded']);
        $this->line('Client reconciliation: '.json_encode($report['client_reconciliation']['totals']));
        $this->line('Company coverage: '.json_encode($report['company_coverage']['counts']));
        $this->line('Reference promote verdict: '.$report['promotion_gates']['overall_reference_promote']);
        $this->line("Output: {$path}");

        return self::SUCCESS;
    }
}
