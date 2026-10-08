<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Illuminate\Support\Facades\DB;

class EmployeeCompensationService
{
    public function record(Employee $employee, float $basicPay, string $effectiveFrom, ?int $adminId = null, string $currency = 'ZMW'): EmployeeCompensation
    {
        return DB::transaction(function () use ($employee, $basicPay, $effectiveFrom, $adminId, $currency) {
            EmployeeCompensation::query()
                ->where('employee_id', $employee->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'effective_to' => $effectiveFrom,
                ]);

            return EmployeeCompensation::create([
                'employee_id' => $employee->id,
                'basic_pay' => $basicPay,
                'effective_from' => $effectiveFrom,
                'is_current' => true,
                'currency' => $currency,
                'status' => 'active',
                'created_by' => $adminId,
            ]);
        });
    }
}
