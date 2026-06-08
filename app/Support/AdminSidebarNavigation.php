<?php

namespace App\Support;

class AdminSidebarNavigation
{
    /**
     * @param  array<int, string>|string|null  $patterns
     */
    public static function matches(array|string|null $patterns): bool
    {
        if ($patterns === null || $patterns === []) {
            return false;
        }

        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function itemIsActive(array $item): bool
    {
        if (static::matches($item['routes'] ?? null)) {
            return true;
        }

        foreach ($item['children'] ?? [] as $child) {
            if (is_array($child) && static::childIsActive($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $child
     */
    public static function childIsActive(array $child): bool
    {
        return static::matches($child['routes'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function groupIsOpen(array $item): bool
    {
        if (empty($item['children'])) {
            return false;
        }

        return static::itemIsActive($item);
    }

    /**
     * Route patterns keyed by menu id, then child label (or "_self" for top-level links).
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function routePatternMap(): array
    {
        return [
            'dashboard' => [
                '_self' => ['admin.dashboard'],
            ],
            'menu-users' => [
                'View Users' => [
                    'admin.users.index',
                    'admin.users.show',
                    'admin.users.edit',
                    'admin.users.update',
                    'admin.users.destroy',
                    'admin.users.export',
                    'admin.users.login-audit',
                    'admin.users.send-password-reset',
                ],
                'Create User' => ['admin.users.create', 'admin.users.store'],
            ],
            'menu-companies' => [
                'View Companies' => [
                    'admin.companies.index',
                    'admin.companies.show',
                    'admin.companies.edit',
                    'admin.companies.update',
                    'admin.companies.destroy',
                    'admin.companies.export',
                    'admin.companies.loan-rate-type',
                    'admin.companies.payment-due-report',
                    'admin.companies.payment-due-report.*',
                ],
                'Register Company' => ['admin.companies.create', 'admin.companies.store'],
            ],
            'menu-customers' => [
                'View Customers' => [
                    'admin.customers.index',
                    'admin.customers.show',
                    'admin.customers.edit',
                    'admin.customers.update',
                    'admin.customers.destroy',
                    'admin.customers.export',
                    'admin.customers.loans',
                    'admin.customers.repayments',
                    'admin.customers.repayments.create',
                    'admin.customers.repayments.store',
                    'admin.customers.login-audit',
                    'admin.customers.change-group',
                    'admin.customers.update-group',
                    'admin.customers.reset-pin',
                    'admin.customers.recalculate-credit-score',
                    'admin.customers.kyc',
                    'admin.customers.kyc.*',
                    'admin.customers.send-message',
                    'admin.customers.upload',
                    'admin.customers.upload.*',
                ],
                'View Groups' => ['admin.customer-groups.*'],
                'Create Customer' => [
                    'admin.customers.create',
                    'admin.customers.store',
                    'admin.customers.select-product-type',
                ],
                'Customer Requests' => ['admin.customer-requests.*'],
                'Fraud & Abuse Protection' => ['admin.fraud-protection.*'],
            ],
            'menu-communications' => [
                'View Communications' => [
                    'admin.communications.index',
                    'admin.communications.show',
                ],
                'Send Communication' => [
                    'admin.communications.create',
                    'admin.communications.store',
                ],
            ],
            'menu-support-tickets' => [
                '_self' => ['admin.support-tickets.*'],
            ],
            'menu-loan-management' => [
                'View Loans' => ['admin.loans.*'],
                'Repayments' => ['admin.repayments.*'],
                'Bulk Repayment' => ['admin.bulk-repayments.*'],
                'PMEC Submissions' => ['admin.pmec-submissions.*'],
                'Loan Application' => [
                    'admin.loan-applications.index',
                    'admin.loan-applications.search-customer',
                    'admin.loan-applications.search-customer-ajax',
                    'admin.loan-applications.loan-details',
                    'admin.loan-applications.calculate-repayment',
                    'admin.loan-applications.store-calculation',
                    'admin.loan-applications.collateral',
                    'admin.loan-applications.calculate-ltv',
                    'admin.loan-applications.store',
                    'admin.loan-applications.review',
                    'admin.loan-applications.store-mou',
                    'admin.loan-applications.review-character',
                    'admin.loan-applications.store-character',
                    'admin.loan-applications.review-government',
                    'admin.loan-applications.store-government',
                ],
                'Group Loan Applications' => ['admin.loan-applications.group-loans.*'],
                'Loan Calculator' => ['admin.loan-calculator.*'],
                'Payment Due Report' => ['admin.payment-due-report.*'],
            ],
            'menu-financial-management' => [
                'Banks' => ['admin.banks.*'],
                'Wallets' => ['admin.wallets.*'],
                'Creditors' => ['admin.creditors.*'],
                'Transactions' => ['admin.financial-transactions.*'],
                'Transfers' => ['admin.transfers.*'],
                'Balance Sheet' => ['admin.financial-statements.balance-sheet'],
                'Cash Flow' => ['admin.financial-statements.cash-flow'],
                'Income Statement' => ['admin.financial-statements.income-statement'],
            ],
            'menu-roles' => [
                'View Roles' => [
                    'admin.roles.index',
                    'admin.roles.show',
                    'admin.roles.edit',
                    'admin.roles.update',
                    'admin.roles.destroy',
                ],
                'Create Role' => ['admin.roles.create', 'admin.roles.store'],
                'Permissions Matrix' => ['admin.roles.index'],
            ],
            'menu-configurations' => [
                'Product Types' => ['admin.loan-products.*'],
                'Collateral Types' => ['admin.loan-products.collateral-types.*'],
                'Interest Rate Types' => ['admin.loan-rate-types.*'],
                'Sectors' => ['admin.sectors.*'],
                'Ministries' => ['admin.ministries.*'],
                'Provinces' => ['admin.provinces.*'],
                'Branches' => ['admin.branches.*'],
                'General Settings' => ['admin.settings.general.*', 'admin.settings.customer-registration.*', 'admin.settings.repayment-reminders.*', 'admin.settings.credit-score.*'],
                'Security Questions' => ['admin.security-questions.*'],
                'Banking Institutions' => ['admin.financial-institutions.*'],
                'Payment Channels' => ['admin.channels.*'],
                'FAQs' => ['admin.faqs.*'],
            ],
            'menu-approvals' => [
                '_self' => ['admin.approvals.*'],
            ],
            'menu-reports' => [
                'Arrears Report' => ['admin.reports.arrears', 'admin.reports.arrears.*'],
                'Disbursements Report' => ['admin.reports.disbursements', 'admin.reports.disbursements.*'],
                'Collections Report' => ['admin.reports.collections', 'admin.reports.collections.*'],
                'Collection Split' => ['admin.reports.collection-split', 'admin.reports.collection-split.*'],
                'Loan Book Report' => ['admin.reports.loan-book', 'admin.reports.loan-book.*'],
                'Loan Performance' => ['admin.reports.loan-performance', 'admin.reports.loan-performance.*'],
                'Branch Report' => ['admin.reports.branches'],
                'Risk Heatmap Dashboard' => ['admin.reports.risk-heatmap'],
                'Relationship Manager Report' => ['admin.reports.relationship-manager', 'admin.reports.relationship-manager.*'],
            ],
            'menu-audit-logs' => [
                '_self' => ['admin.audit-logs.*'],
            ],
            'menu-backups' => [
                '_self' => ['admin.backups.*', 'admin.system.backup.*'],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $navItems
     * @return array<int, array<string, mixed>>
     */
    public static function applyRoutePatterns(array $navItems): array
    {
        $map = static::routePatternMap();

        foreach ($navItems as &$item) {
            $menuKey = $item['id'] ?? ($item['label'] === 'Dashboard' ? 'dashboard' : null);

            if ($menuKey === null || ! isset($map[$menuKey])) {
                continue;
            }

            $patterns = $map[$menuKey];

            if (isset($patterns['_self'])) {
                $item['routes'] = $patterns['_self'];
            }

            if (! empty($item['children'])) {
                foreach ($item['children'] as &$child) {
                    if (! is_array($child)) {
                        continue;
                    }

                    $child['routes'] = $patterns[$child['label']] ?? [];
                }
                unset($child);
            }
        }
        unset($item);

        return $navItems;
    }
}
