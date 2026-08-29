<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;

class AdminCompanyScope
{
    public static function applyLoanFilter(Builder $query, ?Admin $admin): Builder
    {
        $companyFilterId = $admin?->getCompanyFilterId();
        if ($companyFilterId === null) {
            return $query;
        }

        return $query->whereHas('customer', function (Builder $customerQuery) use ($companyFilterId) {
            $customerQuery->where('company_id', $companyFilterId);
        });
    }

    public static function applyCustomerFilter(Builder $query, ?Admin $admin): Builder
    {
        $companyFilterId = $admin?->getCompanyFilterId();
        if ($companyFilterId === null) {
            return $query;
        }

        return $query->where('company_id', $companyFilterId);
    }

    public static function applyRepaymentFilter(Builder $query, ?Admin $admin): Builder
    {
        $companyFilterId = $admin?->getCompanyFilterId();
        if ($companyFilterId === null) {
            return $query;
        }

        return $query->whereHas('customer', function (Builder $customerQuery) use ($companyFilterId) {
            $customerQuery->where('company_id', $companyFilterId);
        });
    }

    public static function rollupCompanyExpression(): string
    {
        $operatorId = OperatorCompany::id();

        if ($operatorId === null) {
            return 'customers.company_id';
        }

        return 'COALESCE(customers.company_id, '.(int) $operatorId.')';
    }
}
