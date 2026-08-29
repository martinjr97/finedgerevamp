# M2 — Active Loan Migration

## Scope

```text
status_code = 301
```

Currently **752** loans (dynamic at runtime).

## Prerequisites

1. `migration:reference-data` (products mapped)
2. `migration:customers --promote` (customer entity maps)

Loans are **BLOCKED** if customer mapping missing — customers are not created implicitly.

## Promotion cohorts

| Cohort | Action |
|--------|--------|
| COHORT_A_AUTO_PROMOTE | PASS replay |
| COHORT_B_OPENING_POSITION_PROMOTE | PASS_WITH_MIGRATION_ADJUSTMENT |
| COHORT_C_MANUAL_REVIEW | Do not promote (e.g. loans 16969, 17617) |
| COHORT_D_BLOCKED | FAIL |

## Dry-run result (sample, before customer promote)

| Metric | Value |
|--------|------:|
| Legacy active loans | 752 |
| Would create | 0 |
| Blocked (missing customer) | 750 |
| Manual review | 2 |

After `migration:customers --promote`, dry-run should show **750 would_create**, **2 manual_review**.

Output: `docs/data-migration/tools/m2-active-loans-dry-run.json`
