<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;

class RelationshipManagerCustomerBranchSync
{
    /**
     * Assign the relationship manager's branch to company customers missing a branch.
     */
    public static function syncForCompany(Company $company, ?int $relationshipManagerId): int
    {
        $branchId = self::relationshipManagerBranchId($relationshipManagerId);

        if ($branchId === null) {
            return 0;
        }

        return Customer::query()
            ->where('company_id', $company->id)
            ->whereNull('branch_id')
            ->update(['branch_id' => $branchId]);
    }

    /**
     * Assign the relationship manager's branch to group customers missing a branch.
     */
    public static function syncForCustomerGroup(CustomerGroup $customerGroup, ?int $relationshipManagerId): int
    {
        $branchId = self::relationshipManagerBranchId($relationshipManagerId);

        if ($branchId === null) {
            return 0;
        }

        return Customer::query()
            ->where('customer_group_id', $customerGroup->id)
            ->whereNull('branch_id')
            ->update(['branch_id' => $branchId]);
    }

    private static function relationshipManagerBranchId(?int $relationshipManagerId): ?int
    {
        if ($relationshipManagerId === null) {
            return null;
        }

        $branchId = Admin::query()
            ->whereKey($relationshipManagerId)
            ->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }
}
