<?php

namespace App\Console\Commands;

use App\Services\Hr\EmployeeLoans\EmployeeLoanInterestAccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueEmployeeLoanInterest extends Command
{
    protected $signature = 'employee-loans:accrue-interest {--date=}';

    protected $description = 'Accrue daily interest for employee loans with daily accrual type';

    public function handle(EmployeeLoanInterestAccrualService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $count = $service->accrueForDate($date);
        $this->info("Employee loan interest accrual complete: {$count} loan(s) for {$date->toDateString()}");

        return self::SUCCESS;
    }
}
