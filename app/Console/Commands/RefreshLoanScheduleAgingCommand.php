<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanPortfolioMaintenanceService;
use Illuminate\Console\Command;

class RefreshLoanScheduleAgingCommand extends Command
{
    protected $signature = 'loans:refresh-schedule-aging {--loan-id= : Single loan id}';

    protected $description = 'Refresh days_overdue and schedule status for active portfolio loans';

    public function handle(LoanPortfolioMaintenanceService $maintenance): int
    {
        $loanId = $this->option('loan-id');

        if ($loanId) {
            $loan = Loan::query()->findOrFail((int) $loanId);
            $updated = $maintenance->refreshScheduleAging($loan);
            $this->info("Refreshed {$updated} schedule row(s) for loan {$loan->loan_number}.");

            return self::SUCCESS;
        }

        $updated = $maintenance->refreshAllActiveScheduleAging();
        $this->info("Refreshed schedule aging for {$updated} schedule row(s).");

        return self::SUCCESS;
    }
}
