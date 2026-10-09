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
            'hr.employee-loans.view',
            'hr.employee-loans.create',
            'hr.employee-loans.update',
            'hr.employee-loans.approve',
            'hr.employee-loans.disburse',
            'hr.employee-loans.repay',
            'hr.employee-loans.settle',
            'hr.employee-loans.reports',
            'hr.employee-loans.financials',
            'hr.settings.view',
            'hr.settings.manage',
        ];
    }
}
