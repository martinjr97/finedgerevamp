<?php

namespace App\Console\Commands;

use App\Services\Loans\LoanArrearsAccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueLoanArrears extends Command
{
    protected $signature = 'loans:accrue-arrears
        {--date= : Accrue through this date (Y-m-d, inclusive)}
        {--from= : Catch-up start date (Y-m-d, inclusive)}
        {--to= : Catch-up end date (Y-m-d, inclusive; defaults to today in Lusaka)}
        {--loan-id= : Limit to one loan}
        {--allow-historical-backfill : Allow accrual dates before ARREARS_ENGINE_EFFECTIVE_DATE}';

    protected $description = 'Accrue daily arrears interest on overdue installments (with catch-up)';

    public function handle(LoanArrearsAccrualService $service): int
    {
        $timezone = config('arrears.timezone', 'Africa/Lusaka');
        $through = $this->option('to')
            ? Carbon::parse($this->option('to'), $timezone)->startOfDay()
            : ($this->option('date')
                ? Carbon::parse($this->option('date'), $timezone)->startOfDay()
                : Carbon::today($timezone));

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), $timezone)->startOfDay()
            : null;

        $loanId = $this->option('loan-id') ? (int) $this->option('loan-id') : null;
        $allowHistorical = (bool) $this->option('allow-historical-backfill');

        if (app()->environment('production') && ! config('arrears.engine_effective_date') && ! $allowHistorical) {
            $this->error('ARREARS_ENGINE_EFFECTIVE_DATE must be set in production before running loans:accrue-arrears.');
            $this->line('Example: ARREARS_ENGINE_EFFECTIVE_DATE=2026-10-08');

            return self::FAILURE;
        }

        $stats = $service->accruePortfolio($through, $from, $loanId, $allowHistorical);

        $this->info('Arrears accrual complete.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Loans scanned', $stats['loans_scanned']],
                ['Overdue installments', $stats['overdue_installments']],
                ['Accrual rows created', $stats['accruals_created']],
                ['Accrual rows adjusted', $stats['accruals_adjusted']],
                ['Skipped duplicates', $stats['accruals_skipped_existing']],
                ['NPL classifications', $stats['loans_classified_npl']],
                ['Total arrears charged', $stats['total_arrears_charged']],
                ['Errors', $stats['errors']],
            ]
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
