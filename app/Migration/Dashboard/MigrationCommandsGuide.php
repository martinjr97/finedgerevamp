<?php

namespace App\Migration\Dashboard;

class MigrationCommandsGuide
{
    /**
     * Phased migration command playbook for CLI execution.
     * Each phase is run separately — never combine into one bulk command.
     *
     * @return list<array{
     *     number: int|string,
     *     title: string,
     *     description: string,
     *     steps: list<array{
     *         label: string,
     *         command: string,
     *         notes?: string,
     *         destructive?: bool,
     *     }>,
     *     gates?: list<string>,
     *     optional?: bool,
     * }>
     */
    public static function phases(): array
    {
        return [
            [
                'number' => '0',
                'title' => 'Pre-flight (read-only)',
                'description' => 'Run before any --promote. Confirms legacy connection, mappings, and promotion gates.',
                'steps' => [
                    [
                        'label' => 'Consistency audit',
                        'command' => 'php artisan migration:audit',
                        'notes' => 'Optional JSON output: php artisan migration:audit --output=docs/data-migration/tools/m2-pre-promotion-audit.json',
                    ],
                    [
                        'label' => 'Current progress snapshot',
                        'command' => 'php artisan migration:status',
                    ],
                ],
            ],
            [
                'number' => '0b',
                'title' => 'Identity resolution',
                'description' => 'Apply approved duplicate-NRC alias maps before customer promotion. Unresolved groups can be resolved on the migration dashboard Identity tab.',
                'steps' => [
                    [
                        'label' => 'Review pending duplicate NRC groups (dashboard)',
                        'command' => 'Open /legacy/migration-dashboard/identity and resolve each pending group (requires migration.manage)',
                        'notes' => 'Not a CLI command — use the Identity tab in the migration dashboard.',
                    ],
                    [
                        'label' => 'Preview resolutions',
                        'command' => 'php artisan migration:identity-resolve',
                    ],
                    [
                        'label' => 'Apply resolutions',
                        'command' => 'php artisan migration:identity-resolve --apply',
                        'destructive' => true,
                    ],
                ],
                'gates' => [
                    'Customer promotion is blocked until duplicate NRC groups are resolved.',
                ],
            ],
            [
                'number' => 1,
                'title' => 'Reference data',
                'description' => 'Products, MOU employers, banks, wallet providers, Marketeer group/markets, branches, and relationship managers.',
                'steps' => [
                    [
                        'label' => 'Dry-run (preview)',
                        'command' => 'php artisan migration:reference-data --dry-run',
                    ],
                    [
                        'label' => 'Promote reference data',
                        'command' => 'php artisan migration:reference-data --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Verify idempotency',
                        'command' => 'php artisan migration:reference-data --dry-run',
                        'notes' => 'Gate: 0 would-create for all reference entities.',
                    ],
                    [
                        'label' => 'Check status',
                        'command' => 'php artisan migration:status',
                    ],
                ],
                'gates' => [
                    'Post-promote dry-run shows 0 would-create.',
                    'MOU employers, MARK-001 group/markets, and treasury mappings present.',
                ],
            ],
            [
                'number' => 2,
                'title' => 'Customers',
                'description' => 'Migrate all legacy customer users into revamp customers with company/group/market links.',
                'steps' => [
                    [
                        'label' => 'Dry-run (preview)',
                        'command' => 'php artisan migration:customers --dry-run',
                    ],
                    [
                        'label' => 'Promote customers',
                        'command' => 'php artisan migration:customers --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Verify idempotency',
                        'command' => 'php artisan migration:customers --dry-run',
                        'notes' => 'Gate: 0 would-create; manual_review = 0 (or documented exceptions).',
                    ],
                    [
                        'label' => 'Check status',
                        'command' => 'php artisan migration:status',
                    ],
                ],
                'gates' => [
                    'company_mapping_pending = 0',
                    'marketeer_market_pending = 0',
                    'Identity resolutions applied before promote.',
                ],
            ],
            [
                'number' => 3,
                'title' => 'Active loans',
                'description' => 'Migrate legacy active loans (status 301) only — not settled/historical book.',
                'steps' => [
                    [
                        'label' => 'Dry-run (preview)',
                        'command' => 'php artisan migration:active-loans --dry-run',
                    ],
                    [
                        'label' => 'Promote active loans',
                        'command' => 'php artisan migration:active-loans --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Verify idempotency',
                        'command' => 'php artisan migration:active-loans --dry-run',
                        'notes' => 'Gate: 0 would-create; manual_review loans excluded.',
                    ],
                    [
                        'label' => 'Check status',
                        'command' => 'php artisan migration:status',
                    ],
                ],
                'gates' => [
                    'Customers promoted first.',
                    'Manual review loans (e.g. 16969, 17617) remain excluded.',
                ],
            ],
            [
                'number' => 4,
                'title' => 'Repayments',
                'description' => 'Promote A_DIRECT and B_RECONSTRUCTED repayments for the active portfolio. C_AMBIGUOUS and D_MANUAL are excluded.',
                'steps' => [
                    [
                        'label' => 'Dry-run (preview)',
                        'command' => 'php artisan migration:repayments --dry-run',
                    ],
                    [
                        'label' => 'Promote repayments',
                        'command' => 'php artisan migration:repayments --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Verify idempotency',
                        'command' => 'php artisan migration:repayments --dry-run',
                        'notes' => 'Gate: would_promote = 0; promoted count stable.',
                    ],
                    [
                        'label' => 'Check status',
                        'command' => 'php artisan migration:status',
                    ],
                ],
                'gates' => [
                    'Active loans promoted first.',
                    'No duplicate LEG-R-* references.',
                ],
            ],
            [
                'number' => 5,
                'title' => 'Reconciliation',
                'description' => 'Read-only portfolio reconciliation — compare legacy effective outstanding vs target.',
                'steps' => [
                    [
                        'label' => 'Run reconciliation',
                        'command' => 'php artisan migration:reconcile',
                    ],
                    [
                        'label' => 'Check status',
                        'command' => 'php artisan migration:status',
                    ],
                ],
                'gates' => [
                    'FAIL = 0 for all auto-migrated active loans.',
                    'Per-loan variance <= ZMW 0.01.',
                ],
            ],
            [
                'number' => 6,
                'title' => 'Financial data (separate track)',
                'description' => 'Treasury categories, expenses, and manual incomes — run after portfolio migration or independently.',
                'optional' => true,
                'steps' => [
                    [
                        'label' => 'Promote expense/income categories',
                        'command' => 'php artisan migration:financial-data --only=categories --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Promote creditors',
                        'command' => 'php artisan migration:financial-data --only=creditors --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Promote physical assets',
                        'command' => 'php artisan migration:financial-data --only=assets --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Dry-run expenses',
                        'command' => 'php artisan migration:financial-data --only=expenses --from-date=YYYY-MM-DD --dry-run',
                        'notes' => 'Set MIGRATION_FINANCIAL_FROM_DATE in .env or pass --from-date.',
                    ],
                    [
                        'label' => 'Promote expenses',
                        'command' => 'php artisan migration:financial-data --only=expenses --from-date=YYYY-MM-DD --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Dry-run manual incomes',
                        'command' => 'php artisan migration:financial-data --only=incomes --from-date=YYYY-MM-DD --dry-run',
                    ],
                    [
                        'label' => 'Promote manual incomes',
                        'command' => 'php artisan migration:financial-data --only=incomes --from-date=YYYY-MM-DD --promote',
                        'destructive' => true,
                    ],
                    [
                        'label' => 'Promote creditor conversion history (audit)',
                        'command' => 'php artisan migration:financial-data --only=creditor_conversions --promote',
                        'destructive' => true,
                    ],
                ],
                'gates' => [
                    'Categories promoted before expenses/incomes.',
                    'Creditors promoted before expenses that reference creditor_id.',
                    'Replace YYYY-MM-DD with your cutover date.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, command: string, notes?: string}>}>
     */
    public static function utilities(): array
    {
        return [
            [
                'title' => 'Subset / batch options',
                'items' => [
                    [
                        'label' => 'Reference data subset (Marketeer only)',
                        'command' => 'php artisan migration:reference-data --only=marketeer --dry-run',
                    ],
                    [
                        'label' => 'Single legacy customer',
                        'command' => 'php artisan migration:customers --legacy-id=123 --dry-run',
                    ],
                    [
                        'label' => 'Single legacy loan',
                        'command' => 'php artisan migration:active-loans --legacy-id=12345 --dry-run',
                    ],
                    [
                        'label' => 'Customer-scoped repayments',
                        'command' => 'php artisan migration:repayments --customer=123 --dry-run',
                    ],
                    [
                        'label' => 'Portfolio corrections (post-migrate)',
                        'command' => 'php artisan migration:correct-portfolio --dry-run',
                    ],
                ],
            ],
            [
                'title' => 'Rollback (destructive)',
                'items' => [
                    [
                        'label' => 'Rollback a specific run',
                        'command' => 'php artisan migration:rollback --run=<uuid>',
                        'notes' => 'Only safe before post-cutover financial activity on migrated loans. Find run UUIDs on the Runs tab.',
                    ],
                ],
            ],
        ];
    }

    public static function executionOrder(): string
    {
        return 'REFERENCE DATA → CUSTOMERS → ACTIVE LOANS → REPAYMENTS → RECONCILIATION';
    }
}
