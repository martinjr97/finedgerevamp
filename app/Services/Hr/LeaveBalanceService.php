<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeLeaveTransaction;
use App\Models\LeaveType;
use Illuminate\Support\Collection;

class LeaveBalanceService
{
    /**
     * @return array<string, array{accrued: float, taken: float, adjustments: float, available: float, name: string}>
     */
    public function balancesForEmployee(Employee $employee): array
    {
        $result = [];
        foreach (LeaveType::query()->where('is_active', true)->orderBy('name')->get() as $type) {
            $balance = $this->balanceForType($employee->id, $type->id);
            $balance['name'] = $type->name;
            $result[$type->code] = $balance;
        }

        return $result;
    }

    /**
     * Available (and summary) balances for many employees — one query for transactions.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, LeaveType>  $leaveTypes
     * @return array<int, array<string, array{accrued: float, taken: float, adjustments: float, available: float, name: string}>>
     */
    public function balancesForEmployees(Collection $employees, Collection $leaveTypes): array
    {
        if ($employees->isEmpty() || $leaveTypes->isEmpty()) {
            return [];
        }

        $employeeIds = $employees->pluck('id');
        $leaveTypeIds = $leaveTypes->pluck('id');

        $grouped = EmployeeLeaveTransaction::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('leave_type_id', $leaveTypeIds)
            ->get()
            ->groupBy(fn (EmployeeLeaveTransaction $row) => $row->employee_id.'_'.$row->leave_type_id);

        $matrix = [];
        foreach ($employees as $employee) {
            foreach ($leaveTypes as $type) {
                $key = $employee->id.'_'.$type->id;
                $summary = $this->summarizeTransactions($grouped->get($key, collect()));
                $summary['name'] = $type->name;
                $matrix[$employee->id][$type->code] = $summary;
            }
        }

        return $matrix;
    }

    /**
     * @return array{accrued: float, taken: float, adjustments: float, available: float}
     */
    public function balanceForType(int $employeeId, int $leaveTypeId): array
    {
        $rows = EmployeeLeaveTransaction::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->get();

        return $this->summarizeTransactions($rows);
    }

    /**
     * @param  Collection<int, EmployeeLeaveTransaction>  $rows
     * @return array{accrued: float, taken: float, adjustments: float, available: float}
     */
    private function summarizeTransactions(Collection $rows): array
    {
        $accrued = round((float) $rows->where('type', EmployeeLeaveTransaction::TYPE_ACCRUAL)->sum('days'), 2);
        $taken = round((float) $rows->where('type', EmployeeLeaveTransaction::TYPE_LEAVE_TAKEN)->sum('days'), 2);
        $adjustments = round((float) $rows->where('type', EmployeeLeaveTransaction::TYPE_ADJUSTMENT)->sum('days'), 2);

        $available = round($rows->sum(function (EmployeeLeaveTransaction $row) {
            $days = (float) $row->days;

            return match ($row->type) {
                EmployeeLeaveTransaction::TYPE_ACCRUAL,
                EmployeeLeaveTransaction::TYPE_CARRY_FORWARD => $days,
                EmployeeLeaveTransaction::TYPE_ADJUSTMENT => $days,
                EmployeeLeaveTransaction::TYPE_REVERSAL => $days,
                EmployeeLeaveTransaction::TYPE_LEAVE_TAKEN => -abs($days),
                default => 0,
            };
        }), 2);

        return [
            'accrued' => $accrued,
            'taken' => $taken,
            'adjustments' => $adjustments,
            'available' => max(0, $available),
        ];
    }

    public function hasSufficientBalance(int $employeeId, int $leaveTypeId, float $days): bool
    {
        return $this->balanceForType($employeeId, $leaveTypeId)['available'] + 0.0001 >= $days;
    }
}
