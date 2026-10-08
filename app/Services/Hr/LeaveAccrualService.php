<?php

namespace App\Services\Hr;

use App\Models\ContractTypeLeaveRule;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeLeaveTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeaveAccrualService
{
    /**
     * @return array{processed: int, skipped: int}
     */
    public function accrueForMonth(?Carbon $month = null): array
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $accrualDate = $month->copy()->endOfMonth()->toDateString();
        $periodKey = $month->format('Y-m');

        $processed = 0;
        $skipped = 0;

        Employee::query()
            ->active()
            ->with(['activeContract.contractType.leaveRules.leaveType'])
            ->chunkById(100, function ($employees) use ($accrualDate, $periodKey, &$processed, &$skipped) {
                foreach ($employees as $employee) {
                    $contract = $employee->activeContract;
                    if (! $contract || ! $contract->contractType?->leave_accrual_enabled) {
                        $skipped++;

                        continue;
                    }

                    foreach ($contract->contractType->leaveRules as $rule) {
                        if (! $rule->appliesToEmployeeGender($employee->gender)) {
                            continue;
                        }

                        if (! $rule->requires_accrual || (float) $rule->days_per_month <= 0) {
                            continue;
                        }

                        $exists = EmployeeLeaveTransaction::query()
                            ->where('employee_id', $employee->id)
                            ->where('leave_type_id', $rule->leave_type_id)
                            ->where('type', EmployeeLeaveTransaction::TYPE_ACCRUAL)
                            ->where('description', 'Monthly accrual '.$periodKey)
                            ->exists();

                        if ($exists) {
                            $skipped++;

                            continue;
                        }

                        EmployeeLeaveTransaction::create([
                            'employee_id' => $employee->id,
                            'leave_type_id' => $rule->leave_type_id,
                            'transaction_date' => $accrualDate,
                            'type' => EmployeeLeaveTransaction::TYPE_ACCRUAL,
                            'days' => $rule->days_per_month,
                            'description' => 'Monthly accrual '.$periodKey,
                        ]);

                        $processed++;
                    }
                }
            });

        return ['processed' => $processed, 'skipped' => $skipped];
    }
}
