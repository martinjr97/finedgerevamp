<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Support\Facades\DB;

class EmployeeContractService
{
    public function activate(EmployeeContract $contract, ?int $adminId = null): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $adminId) {
            EmployeeContract::query()
                ->where('employee_id', $contract->employee_id)
                ->where('id', '!=', $contract->id)
                ->where('status', EmployeeContract::STATUS_ACTIVE)
                ->update(['status' => EmployeeContract::STATUS_RENEWED]);

            $contract->update([
                'status' => EmployeeContract::STATUS_ACTIVE,
                'created_by' => $adminId ?? $contract->created_by,
            ]);

            return $contract->fresh();
        });
    }

    public function validateNoOverlappingActive(Employee $employee, string $startDate, ?string $endDate, ?int $ignoreContractId = null): bool
    {
        $query = EmployeeContract::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeContract::STATUS_ACTIVE);

        if ($ignoreContractId) {
            $query->where('id', '!=', $ignoreContractId);
        }

        return ! $query->exists();
    }
}
