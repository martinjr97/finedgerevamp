<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\EmployeeLoan;
use App\Support\PermissionMatrix;

class EmployeeLoanPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.view');
    }

    public function view(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.create');
    }

    public function update(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        if ($this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.update')) {
            return true;
        }

        if ($employeeLoan->status !== EmployeeLoan::STATUS_DRAFT) {
            return false;
        }

        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.create')
            && (int) $employeeLoan->created_by === (int) $admin->id;
    }

    public function approve(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.approve');
    }

    public function disburse(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.disburse');
    }

    public function repay(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.repay');
    }

    public function settle(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.settle');
    }

    public function viewReports(Admin $admin): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.reports');
    }

    public function viewFinancials(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasEmployeeLoanAccess($admin, 'hr.employee-loans.financials');
    }

    public function delete(Admin $admin, EmployeeLoan $employeeLoan): bool
    {
        if ($employeeLoan->status !== EmployeeLoan::STATUS_DRAFT) {
            return false;
        }

        return $this->update($admin, $employeeLoan);
    }

    protected function hasEmployeeLoanAccess(Admin $admin, string $permission): bool
    {
        if ($admin->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)) {
            return true;
        }

        return $admin->can($permission);
    }
}
