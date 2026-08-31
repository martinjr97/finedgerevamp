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
        $this->printStatusSection($report['reference_data']);

        $this->line('CUSTOMERS');
        $this->printStatusSection($report['customers']);

        $this->line('ACTIVE LOANS');
        $this->printStatusSection($report['active_loans']);

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

    /**
     * @param  array<string, mixed>  $section
     */
    private function printStatusSection(array $section): void
    {
        foreach ($section as $key => $value) {
            if (is_array($value)) {
                $this->line("  {$key}:");
                foreach ($value as $nestedKey => $nestedValue) {
                    $this->line('    '.$nestedKey.': '.$this->formatStatusValue($nestedValue));
                }

                continue;
            }

            $this->line("  {$key}: ".$this->formatStatusValue($value));
        }
    }

    private function formatStatusValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
