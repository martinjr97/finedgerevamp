<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const SUPER_ADMIN_ROLE = 'super-admin';
    public const IT_ROLE = 'IT Admin';
    public const GROUP_LOANS_ASSIGN_RELATIONSHIP_MANAGER_PERMISSION = 'can assign relationship manager to group';
    private const ADMIN_GUARD = 'admin';

    /**
     * Permission slugs that the IT role must not have (e.g. loan approval).
     *
     * @return array<int, string>
     */
    public static function itRoleExcludedPermissions(): array
    {
        return [
            'loans.approve',
            'loans.update-payment-details',
            'approvals.approve',
            'approvals.reject',
        ];
    }

    /**
     * Resource => permitted actions matrix.
     *
     * @return array<string, string[]>
     */
	    public static function permissionMatrix(): array
	    {
	        return [
            'admins' => ['view', 'create', 'update', 'delete'],
            'customers' => ['view', 'create', 'update', 'delete', 'reset-pin', 'send-message', 'loans', 'repayments', 'change-group', 'export'],
            'customer-requests' => ['view', 'update', 'approve', 'reject', 'revert'],
            'companies' => ['view', 'create', 'update', 'delete', 'export'],
            'loan-products' => ['view', 'create', 'update', 'delete'],
            'loan-rate-types' => ['view', 'create', 'update', 'delete'],
            'sectors' => ['view', 'create', 'update', 'delete'],
            'provinces' => ['view', 'create', 'update', 'delete'],
            'districts' => ['view', 'create', 'update', 'delete'],
            'ministries' => ['view', 'create', 'update', 'delete'],
            'loan-purposes' => ['view', 'create', 'update', 'delete'],
            'security-questions' => ['view', 'create', 'update', 'delete'],
            'channels' => ['view', 'create', 'update', 'delete'],
	            'financial-institutions' => ['view', 'create', 'update', 'delete'],
	            'wallet-providers' => ['view', 'create', 'update', 'delete'],
	            'permissions' => ['view', 'update'],
	            'roles' => ['view', 'create', 'update', 'delete'],
            'audit-logs' => ['view'],
            'reports' => ['view'],
            'loans' => ['view', 'create', 'approve', 'reject', 'export', 'backfill-repayment', 'disburse', 'update-payment-details'],
            'loan' => ['extend'],
            'approvals' => ['view', 'approve', 'reject'],
            'repayments' => ['view', 'create', 'approve', 'reject', 'process', 'export', 'refund'],
            'bulk-repayments' => ['view', 'process'],
            'pmec_submissions' => ['view', 'create', 'export', 'mark_failed'],
            'financial-transactions' => ['view', 'create', 'delete', 'export'],
            'financial-statements' => ['view'],
            'transfers' => ['view', 'create', 'approve', 'reject'],
            'banks' => ['view', 'create', 'update', 'delete'],
            'wallets' => ['view', 'create', 'update', 'delete'],
            'creditors' => ['view', 'create', 'update', 'delete'],
            'customer-groups' => ['view', 'create', 'update', 'delete'],
            'branches' => ['view', 'create', 'update', 'delete'],
            'settings' => ['view', 'update'],
            'fraud-protection' => ['view', 'clear'],
            'kyc' => ['view', 'create', 'update'],
            'communications' => ['view', 'create', 'send'],
            'loan-applications' => ['view', 'create'],
            'faqs' => ['view', 'create', 'update', 'delete'],
            'backups' => ['view', 'create', 'download'],
        ];
    }

    /**
     * Flattened permission slugs, e.g. admins.view.
     *
     * @return array<int, string>
     */
    public static function defaultPermissions(): array
    {
        $matrixPermissions = collect(self::permissionMatrix())
            ->flatMap(
                fn (array $actions, string $resource) => collect($actions)->map(
                    fn (string $action) => "{$resource}.{$action}"
                )
            )
            ->values();

        return $matrixPermissions
            ->merge([
                self::GROUP_LOANS_ASSIGN_RELATIONSHIP_MANAGER_PERMISSION,
            ])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Practical role definitions for admins.
     *
     * @return array<string, array<int, string>>
     */
    public static function roleDefinitions(): array
    {
        return [
            'system-admin' => self::defaultPermissions(),
            'relationship-manager' => [
                'companies.view',
                'companies.update',
                'customers.view',
                'customers.update',
                'loan-products.view',
                'loan-rate-types.view',
                'approvals.view',
            ],
            'company-admin' => [
                'customers.view',
                'customers.create',
                'customers.update',
                'companies.view',
                'loan-products.view',
                'loan-rate-types.view',
                'loans.view',
                'loans.create',
                'loan.extend',
                'reports.view',
            ],
            'loan-officer' => [
                'customers.view',
                'customers.update',
                'loan-products.view',
                'loans.view',
                'loans.create',
                'loans.approve',
                'loans.update-payment-details',
                'loan.extend',
                'approvals.view',
                'repayments.view',
                'repayments.refund',
            ],
            'collections-officer' => [
                'customers.view',
                'customers.repayments',
                'loan-products.view',
                'loans.view',
                'loans.reject',
                'repayments.view',
                'repayments.create',
                'repayments.approve',
                'repayments.reject',
                'repayments.process',
                'repayments.refund',
            ],
            'auditor' => [
                'customers.view',
                'companies.view',
                'loan-products.view',
                'loan-rate-types.view',
                'loans.view',
                'approvals.view',
                'audit-logs.view',
                'reports.view',
                'faqs.view',
            ],
            'support-analyst' => [
                'admins.view',
                'customers.view',
                'companies.view',
                'loan-products.view',
                'loan-rate-types.view',
                'reports.view',
            ],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionModels = collect(self::defaultPermissions())
            ->mapWithKeys(fn (string $permission) => [
                $permission => Permission::findOrCreate($permission, self::ADMIN_GUARD),
            ]);

        foreach (self::roleDefinitions() as $roleSlug => $permissionSlugs) {
            $role = Role::findOrCreate($roleSlug, self::ADMIN_GUARD);
            $assigned = collect($permissionSlugs)
                ->filter(fn (string $slug) => isset($permissionModels[$slug]))
                ->map(fn (string $slug) => $permissionModels[$slug])
                ->values();

            $role->syncPermissions($assigned);
        }

        $superAdminRole = Role::findOrCreate(self::SUPER_ADMIN_ROLE, self::ADMIN_GUARD);
        $superAdminRole->syncPermissions($permissionModels->values());

        $itPermissionSlugs = collect(self::defaultPermissions())
            ->diff(self::itRoleExcludedPermissions())
            ->values()
            ->all();
        $itPermissions = collect($itPermissionSlugs)
            ->filter(fn (string $slug) => isset($permissionModels[$slug]))
            ->map(fn (string $slug) => $permissionModels[$slug])
            ->values();
        $itRole = Role::findOrCreate(self::IT_ROLE, self::ADMIN_GUARD);
        $itRole->syncPermissions($itPermissions);
    }
}
