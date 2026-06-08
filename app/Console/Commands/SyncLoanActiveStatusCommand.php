<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;

class SyncLoanActiveStatusCommand extends Command
{
    protected $signature = 'loans:sync-active-status';

    protected $description = 'Set status to active for loans that are disbursed but still marked approved';

    public function handle(): int
    {
        $updated = Loan::syncActiveStatusForDisbursedLoans();

        $this->info("Updated {$updated} loan(s) to active status.");

        return self::SUCCESS;
    }
}
