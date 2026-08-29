# M2 — Customer Migration

## Scope

**All** legitimate legacy loan-system customers — not limited to active borrowers.

## Source (corrected)

- Primary: **`customers`** table (1,936 rows)
- Identity: joined **`users`** row via `user_id`
- **Do not** iterate `users` alone — 21 admin/staff users have no `customers` row

## Stable keys

```text
source_system = finedge_legacy
legacy_user_id
legacy_customer_id
```

Stored in `customers.metadata` and `migration_entity_maps`.

## Exclusions

Users **without** a `customers` row (21 total): admin/staff/system accounts identified by Spatie roles or system account heuristics. Legacy `status_code=600` means **active customer**, not staff.

## Company link

| Product / client type | company_id |
|-----------------------|------------|
| GOV-001 / GRZ client (id 8) | null — intentional |
| MOU / salary_based employer | mapped company (after reference-data promote) |
| CHAR-001 / MARK-001 | null — legitimate |

### Company coverage buckets (must sum to 1,936)

| Bucket | Count |
|--------|------:|
| Company linked (MOU, map exists) | 29 |
| Government intentional no-company | 1,060 |
| No company legitimate (CHAR/MARK) | 341 |
| Company mapping pending | 506 |
| Manual review | 0 |

506 pending resolves when reference-data `--promote` creates 29 employer companies.

| Marketeer customers | 35 |
| Marketeer market pending | 35 (until `--only=marketeer` promote) |

Marketeer: `company_id=null`, market via `MarketeerCustomerDetail`. See `M2-MARKETEER-TARGET-DESIGN.md`.

## Product assignment

- **`Customer.loan_product_id`**: legacy client default (schema required)
- **`Loan.loan_product_id`**: authoritative at loan migration

## Matching rules

- Explicit `migration_entity_maps` first
- NRC match (HIGH confidence)
- Employee number match
- **No auto-match on phone/email alone** → MANUAL_REVIEW

## Dry-run result (post-audit)

| Metric | Value |
|--------|------:|
| Legacy users total | 1,957 |
| True customers identified | 1,936 |
| Excluded admin/staff | 21 |
| Would create | 1,922 |
| Matched existing | 14 |
| Company linked (MOU) | 29 |
| Government intentional no-company | 1,060 |
| Legitimate no-company | 341 |
| Company mapping pending | 506 |

## Promotion gate

`migration:customers --promote` blocked until duplicate NRC users 14/19 resolved. Run `migration:audit` first.

## Idempotency

Second run with `--promote` after first promote: **0 duplicates** (entity map + metadata guard).

Output: `docs/data-migration/tools/m2-customers-dry-run.json`

See also: `docs/data-migration/M2-PRE-PROMOTION-AUDIT.md`
