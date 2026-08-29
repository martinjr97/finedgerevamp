# M2 — Phased Migration Commands

Controlled domain-by-domain migration extending M0/M1/M1.1 infrastructure.

## Execution order (mandatory)

```text
REFERENCE DATA  →  ALL CUSTOMERS  →  ACTIVE LOANS  →  REPAYMENTS  →  RECONCILIATION
```

Do **not** combine phases into one bulk command.

## Commands

| Phase | Command | Write flag |
|-------|---------|------------|
| 1 Reference | `php artisan migration:reference-data --dry-run` | `--promote` |
| 2 Customers | `php artisan migration:customers --dry-run` | `--promote` |
| 3 Active loans | `php artisan migration:active-loans --dry-run` | `--promote` |
| 4 Repayments | `php artisan migration:repayments --dry-run` | `--promote` |
| 5 Reconcile | `php artisan migration:reconcile` | read-only |
| Status | `php artisan migration:status` | read-only |
| Audit | `php artisan migration:audit` | read-only pre-promotion audit |
| Rollback | `php artisan migration:rollback --run=<uuid>` | destructive (created records only) |

Marketeer reference subset:

```bash
php artisan migration:reference-data --only=marketeer --dry-run
```

Migrates default group (`MRKT-LEGACY`) and legacy markets before customers.

### Common options

```bash
--run=<uuid>      # reuse or track a migration run
--legacy-id=      # single legacy entity
--customer=       # legacy user id filter
--limit=          # batch limit
--only=           # reference-data subset (products|companies|banks|wallet_providers)
```

Legacy M1 commands remain available (`migration:m1-analyze`, `migration:m1-replay`, etc.).

## Architecture

```text
app/Migration/Phases/
├── MigrationRunManager.php
├── MigrationEntityMapRepository.php   # durable cross-run mappings
├── MigrationDependencyGate.php
├── ReferenceDataMigrator.php
├── CustomerMigrator.php
├── ActiveLoanMigrator.php
├── RepaymentMigrator.php
├── MigrationReconciliationReader.php
├── MigrationStatusService.php
├── MigrationRollbackService.php
├── PrePromotionAuditService.php      # read-only consistency audit
├── MigrationPromotionGate.php        # --promote validation gates
└── Support/
    ├── CompanyMatcher.php
    ├── CustomerMatcher.php
    ├── ReferenceMatcher.php
    └── RepaymentManualClassifier.php
```

## Mapping tables

| Table | Purpose |
|-------|---------|
| `migration_entity_maps` | Durable legacy → target mappings (survives reruns) |
| `migration_created_records` | Records created per run (for rollback) |
| `migration_runs` | Run UUID, phase, summary |
| `migration_companies/customers/loans/repayments` | Per-run staging audit |

## Idempotency

- **MATCH → MAP → SKIP CREATE** for existing target records
- Entity maps prevent duplicate creates on rerun
- Target authoritative values are never overwritten from legacy on rerun

## Dry-run outputs

JSON summaries written to `docs/data-migration/tools/m2-*-dry-run.json`.

## Pre-promotion audit

Before any `--promote`:

```bash
php artisan migration:audit --output=docs/data-migration/tools/m2-pre-promotion-audit.json
```

See `docs/data-migration/M2-PRE-PROMOTION-AUDIT.md` for full reconciliation report.

## Promotion gates

`--promote` on reference-data, customers, and active-loans enforces gates via `MigrationPromotionGate`. Customers promotion is blocked while duplicate NRC users 14/19 share target customer 7.
