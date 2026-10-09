<?php

namespace App\Console\Commands;

use App\Services\Hr\EmployeeLoans\EmployeeLoanScheduleService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RefreshEmployeeLoanScheduleAgingCommand extends Command
{
    protected $signature = 'employee-loans:refresh-aging {--date=}';

    protected $description = 'Refresh overdue status on employee loan payment schedules';

    public function handle(EmployeeLoanScheduleService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $updated = $service->refreshAging($date);
        $this->info("Updated {$updated} employee loan schedule row(s) as of {$date->toDateString()}");

        return self::SUCCESS;
    }
}
