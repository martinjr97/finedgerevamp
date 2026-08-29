# Local Migration Rehearsal Report

**Date:** 2026-08-28  
**Environment:** LOCAL ONLY (`APP_ENV=local`)  
**Verdict:** `LOCAL MIGRATION REHEARSAL PASSED`

## Safety check

| Role | Database |
|---|---|
| Legacy (read-only) | `finedge` |
| Revamp target | `finedgerevamp` |
| Production? | **NO** — localhost dev databases |

## Pre-migration backup

| Field | Value |
|---|---|
| Target database | `finedgerevamp` |
| Backup path | `storage/app/migration-backups/finedge-revamp-before-migration-20260828-152224.sql` |
| Backup timestamp | 2026-08-28 15:22:24 |
| Git commit | `930bd5fc690eeabd8317306bf9d1ea93227ca3ed` |
| Migration schema | `2026_08_22_120000_add_migration_entity_maps_and_run_uuid` |

## Phase 1 — Reference data

### Client classification (38 legacy clients)

| Classification | Count |
|---|---:|
| MOU_REAL_EMPLOYER | 26 |
| GOVERNMENT_PRODUCT_PLACEHOLDER | 1 |
| MARKETEER_PRODUCT_PLACEHOLDER | 1 |
| CHARACTER_PRODUCT_PLACEHOLDER | 5 |
| UNUSED | 3 |
| AMBIGUOUS_MANUAL_REVIEW | 2 |

### Promotion result (run `f32cdf74-b000-431b-8a01-02702b712496`)

| Entity | Matched | Created | Skipped |
|---|---:|---:|---|
| Products | 4 | 0 | — |
| MOU companies | 2 | 24 | 7 placeholders/unused/manual |
| Banks | 1 | 0 | 1 manual |
| Wallet providers | 3 | 0 | 2 skipped |
| Marketeer group | 1 | 0 | — |
| Markets | 2 | 0 | — |

Post-promote dry-run: **0 would-create** for all reference entities.

## Phase 2 — Customers

### Source population

| Metric | Count |
|---|---:|
| Legacy users | 1,957 |
| True customer rows | 1,936 |
| Admin/staff excluded | 21 |
| Unique intended target customers | **1,934** |
| Identity alias groups | 2 (users 14+19, 126+127) |

### Promotion result (run `6b172ac1-2d3e-4484-a6ac-d1f22ec69909`)

| Metric | Count |
|---|---:|
| Created (this run) | 955 |
| Matched existing | 981 |
| Manual review | 0 |
| Company mapping pending | 0 |
| Marketeer market mapped | 35/35 |

### Company coverage

| Bucket | Count |
|---|---:|
| MOU company linked | 522 |
| Government intentional no-company | 1,060 |
| Character intentional no-company | 315 |
| Marketeer intentional no-company | 35 |
| Company manual review | 4 (Finedge Test/Stuff) |

### Identity aliases verified

| Legacy users | Target customer |
|---|---:|
| 14 + 19 | 7 |
| 126 + 127 | 62 |

Distinct target customer records: **1,934**

### Follow-ups (non-blocking)

- 4 Government customers have `company_id` set (1,056/1,060 correctly null)
- 2 Marketeer customers missing `marketeer_customer_details` rows
- 3 pre-existing pilot customers inflate total row count to 1,937

## Phase 3 — Active loans

### Dry-run

| Metric | Count |
|---|---:|
| Legacy active (301) | 752 |
| Promotable | 750 |
| Manual review | 2 (loans 16969, 17617) |

### Promotion result (run `ece76c6a-377b-4cac-9957-b5cc52ac4c46`)

| Metric | Count |
|---|---:|
| Created | 739 |
| Matched existing (pilot) | 11 |
| Manual excluded | 2 |

### By product (migrated active loans)

| Product | Loans | Reconciliation |
|---|---:|---|
| MOU-001 | 154 | 154 PASS |
| GOV-001 | 430 | 429 PASS |
| CHAR-001 | 144 | 143 PASS |
| MARK-001 | 24 | 24 PASS |

## Phase 4 — Repayments

### Attribution (active-portfolio scope)

| Class | Count | Action |
|---|---:|---|
| A_DIRECT | 6,541 | Promoted |
| B_RECONSTRUCTED | 2,600 | Promoted |
| C_AMBIGUOUS | 2 | Excluded |
| D_MANUAL | 4,673 | Excluded |

### Promotion result (run `88f08e1f-c865-4c04-b9f4-9547f2da978f`)

| Metric | Count |
|---|---:|
| Promoted | 9,141 |
| Excluded ambiguous | 2 |
| Excluded manual | 4,673 |
| Blocked missing loan | 3 |

Migrated payment total (`LEG-R-*`): **ZMW 6,917,083.27**

Post-promote dry-run: **would_promote = 0**, **promoted (matched) = 9,141**

## Phase 5 — Reconciliation

```
loans_checked: 750
PASS: 750
FAIL: 0
MANUAL_REVIEW: 0
```

Tolerance: `ABS(legacy_effective - target_outstanding) <= 0.01` per loan.

## Phase 7 — Idempotency (post-migration dry-runs)

| Phase | Would-create | Matched existing |
|---|---:|---:|
| reference-data | 0 | all entities |
| customers | 0 | 1,936 |
| active-loans | 0 | 750 |
| repayments | 0 | 9,141 |

## Code fixes applied during rehearsal

1. `ReferenceDataMigrator` — added missing `CREATED` stat key
2. `MigrationEntityMapRepository::find()` — resolve customer maps with `legacy_secondary` (pilot maps)
3. `ActiveLoanMigrator` — match existing `LEG-{id}` loans before create
4. `CustomerMigrator` — unique phone/email collision handling
5. `RepaymentMigrator` — idempotent dry-run via existing `LEG-R-*` references
6. `MigrationStatusService` — enhanced dashboard output

## Tests

```
MigrationPhaseTest: 20 passed (36 assertions)
```

## Manual cases (excluded from auto-migration)

See `PRODUCTION-MANUAL-REVIEW.md`

## Marketeer new origination

MARK-001 historical migration is complete. **New Marketeer origination remains disabled** until 4-week schedule configuration is implemented (weeks 1–3 interest only, week 4 principal + interest).
