# Legacy Migration Dashboard

Temporary read-only admin UI for monitoring the FinEdge legacy → revamp data migration.

## Route

Base path (requires admin login):

```text
/legacy/migration-dashboard
```

Registered in `routes/legacy-migration-dashboard.php` and loaded from `routes/web.php`.

## Feature flag

Set in `.env`:

```env
LEGACY_MIGRATION_DASHBOARD_ENABLED=true
```

Config: `config/migration-dashboard.php` → `migration-dashboard.enabled`

When `false`, all dashboard routes return **404** via `EnsureLegacyMigrationDashboardEnabled` middleware.

## Permissions

Uses existing Spatie admin guard permissions:

| Permission | Purpose |
|------------|---------|
| `migration.view` | Read access to all dashboard pages |
| `migration.manage` | Reserved for future safe correction actions (not implemented yet) |

`super-admin` receives all permissions including `migration.view` via `PermissionSeeder`.

Middleware stack:

```text
auth:admin → password.changed → legacy.migration.dashboard → migration.view (controller)
```

## Pages

| Route | Purpose |
|-------|---------|
| `/legacy/migration-dashboard` | Home summary, phase progress, repayment classes |
| `/legacy/migration-dashboard/runs` | Migration run history |
| `/legacy/migration-dashboard/runs/{id}` | Run detail, created records, entity maps |
| `/legacy/migration-dashboard/mappings` | Reference entity maps (products, companies, banks, etc.) |
| `/legacy/migration-dashboard/companies` | Legacy client classification |
| `/legacy/migration-dashboard/marketeers` | MARK-001 / market mappings |
| `/legacy/migration-dashboard/customers` | Customer staging rows |
| `/legacy/migration-dashboard/customers/{legacyUserId}` | Customer audit detail |
| `/legacy/migration-dashboard/identity` | Approved NRC alias resolutions |
| `/legacy/migration-dashboard/loans` | Active loan migration / replay |
| `/legacy/migration-dashboard/loans/{legacyLoanId}` | Loan detail |
| `/legacy/migration-dashboard/repayments` | Repayment attribution |
| `/legacy/migration-dashboard/repayments/{legacyRepaymentId}` | Allocations |
| `/legacy/migration-dashboard/exceptions` | Manual review / exceptions |
| `/legacy/migration-dashboard/reconciliation` | Portfolio reconciliation snapshot |

## Data sources

- `migration_runs`, `migration_entity_maps`, `migration_created_records`
- Staging tables: `migration_customers`, `migration_loans`, `migration_repayments`, `migration_companies`, etc.
- `migration_loan_replay_results`, `migration_repayment_allocations`
- `MigrationStatusService`, `MigrationReconciliationReader`
- `CustomerIdentityResolutionRegistry` for approved alias merges
- Legacy DB (read-only via `LegacyConnection`) for company catalog counts when available

## Security notes

- No passwords, API keys, wallet secrets, or `.env` values are rendered.
- Run summaries strip sensitive keys before display.
- Treasury bank/wallet rows are labeled as reference mappings, not customer accounts.
- **No** `--promote`, rollback, or migration artisan commands are exposed in the browser.

## Filters & pagination

List pages use server-side Laravel pagination (default 25 per page). Filters include status, classification, run ID, and identifier search.

## Exports

Not implemented in v1. Use existing CLI/report outputs or add CSV later using admin export patterns if needed.

## Known limitations

- Company page loads legacy clients in memory when legacy DB is available (acceptable for current portfolio size).
- Exceptions are derived from staging `manual_review` / `exception` columns — there is no separate `migration_exceptions` table.
- Customer exposure reconciliation by customer is partial; full per-customer exposure view can be extended.
- Read-only: mapping corrections must still be applied via migration tooling / registry updates.

## Removal plan

After production cutover:

1. Set `LEGACY_MIGRATION_DASHBOARD_ENABLED=false`
2. Remove sidebar link, routes, controller, views, and `app/Migration/Dashboard/*` report services
3. Drop `migration.view` / `migration.manage` from `PermissionSeeder` if no longer needed
4. Archive this document under `docs/data-migration/archive/`

## Tests

```bash
php artisan test --filter=LegacyMigrationDashboardTest
```
