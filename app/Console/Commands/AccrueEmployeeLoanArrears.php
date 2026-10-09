<?php

namespace App\Console\Commands;

use App\Services\Hr\EmployeeLoans\EmployeeLoanArrearsAccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueEmployeeLoanArrears extends Command
{
    protected $signature = 'employee-loans:accrue-arrears {--date=}';

    protected $description = 'Accrue arrears interest on overdue employee loan installments';

    public function handle(EmployeeLoanArrearsAccrualService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $count = $service->accrueForDate($date);
        $this->info("Employee loan arrears accrual rows created: {$count} on {$date->toDateString()}");

        return self::SUCCESS;
    }
}
