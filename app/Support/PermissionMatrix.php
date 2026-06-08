<?php

namespace App\Support;

use Database\Seeders\PermissionSeeder;

class PermissionMatrix
{
    public const SUPER_ADMIN_ROLE = PermissionSeeder::SUPER_ADMIN_ROLE;

    /**
     * Returns grouped permissions for UI consumption.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public static function grouped(): array
    {
        $matrix = PermissionSeeder::permissionMatrix();

        $grouped = [];

        foreach ($matrix as $resource => $actions) {
            $title = self::label($resource);
            foreach ($actions as $action) {
                $grouped[$title][] = [
                    'name' => "{$resource}.{$action}",
                    'label' => self::actionLabel($action),
                ];
            }
        }

        $grouped['Group Loans'][] = [
            'name' => PermissionSeeder::GROUP_LOANS_ASSIGN_RELATIONSHIP_MANAGER_PERMISSION,
            'label' => 'Assign Relationship Manager To Group',
        ];

        return $grouped;
    }

    private static function label(string $resource): string
    {
        return match ($resource) {
            'admins' => 'Administrators',
            'customers' => 'Customers',
            'companies' => 'Companies',
            'loan-products' => 'Loan Products',
            'loan-rate-types' => 'Loan Rate Types',
            'permissions' => 'Permissions',
            'roles' => 'Roles',
            'reports' => 'Reports',
            'loans' => 'Loans & Approvals',
            'loan' => 'Loans & Approvals',
            default => ucfirst(str_replace('-', ' ', $resource)),
        };
    }

    private static function actionLabel(string $action): string
    {
        return match ($action) {
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'reject' => 'Reject',
            default => ucfirst($action),
        };
    }
}
