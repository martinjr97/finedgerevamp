<?php

namespace App\Support;

class HrPermissions
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'hr.dashboard.view',
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.update',
            'hr.employees.delete',
            'hr.employee.compensation.view',
            'hr.employee.compensation.manage',
            'hr.employee.bank.view',
            'hr.employee.bank.manage',
            'hr.departments.view',
            'hr.departments.manage',
            'hr.positions.view',
            'hr.positions.manage',
            'hr.contract-types.view',
            'hr.contract-types.manage',
            'hr.contracts.view',
            'hr.contracts.manage',
            'hr.leave.view',
            'hr.leave.apply',
            'hr.leave.approve',
            'hr.leave.manage',
            'hr.leave-balances.view',
            'hr.leave-balances.adjust',
            'hr.settings.view',
            'hr.settings.manage',
        ];
    }
}
