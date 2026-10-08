<?php

namespace App\Console\Commands;

use App\Services\Hr\LeaveAccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HrAccrueLeaveCommand extends Command
{
    protected $signature = 'hr:accrue-leave {--month= : Accrual month in YYYY-MM format}';

    protected $description = 'Post monthly leave accruals for active employees (not scheduled by default).';

    public function handle(LeaveAccrualService $service): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $result = $service->accrueForMonth($month);

        $this->info("Leave accrual for {$month->format('Y-m')}: processed {$result['processed']}, skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
