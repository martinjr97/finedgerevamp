# M2 Legacy Client Classification

**Date:** 2026-08-22  
**Status:** Analysis complete — no customer promotion performed

## Business rule

Only genuine **MOU employer** legacy `clients` records become target `companies`.

Government, Marketeer, and Character legacy clients are **product/model placeholders** and must **not** create companies or cause `company_mapping_pending` for their customers.

## Classification summary (38 legacy clients)

| Classification | Count | Company action |
|---|---:|---|
| MOU_REAL_EMPLOYER | 26 | MATCH_EXISTING (1) or CREATE (25) |
| GOVERNMENT_PRODUCT_PLACEHOLDER | 1 | SKIP_GOVERNMENT_PLACEHOLDER |
| MARKETEER_PRODUCT_PLACEHOLDER | 1 | SKIP_MARKETEER_PLACEHOLDER |
| CHARACTER_PRODUCT_PLACEHOLDER | 5 | SKIP_CHARACTER_PLACEHOLDER |
| UNUSED | 3 | SKIP_UNUSED |
| AMBIGUOUS_MANUAL_REVIEW | 2 | MANUAL_REVIEW |

## Evidence signals

| Signal | Use |
|---|---|
| `clients.product_type = salary_based` + predominantly salary loans | MOU employer |
| `clients.product_type = character_based` OR name contains "character" OR >50% non-salary/non-gov loans | Character placeholder |
| `clients.product_type = marketize_based` OR reg `MKT-2025-001` | Marketeer placeholder |
| Government placeholder heuristics (GRZ, gvnt_loan dominance) | Government placeholder |
| `customer_count = 0` | Unused |
| Internal/test names (Finedge Test, Finedge Stuff) | Manual review |

### Legacy code paths

- `ClientController`: splits UI into `salary_based` employers vs `character_based` **agents**
- Marketeer onboarding assigns `client_id` to Marketize client (`MKT-2025-001` / id 36) and sets `is_marketize_customer`
- `LoanWaiverController`: branches on `product_type` (salary / character / marketize)

## MOU employer list (reference-data target set)

| legacy_client_id | legacy_name | customers | active | action | target_company_id |
|---:|---|---:|---:|---|---:|
| 11 | Starlabs Limited | 28 | 11 | MATCH_EXISTING | 8 |
| 10 | Fairview Hospital | 10 | 1 | CREATE | — |
| 12 | Kibbutz Academy | 9 | 2 | CREATE | — |
| 13 | Legacy Manufacturing Limited | 75 | 29 | CREATE | — |
| 14 | Copperstone University | 10 | 5 | CREATE | — |
| 15 | Rose of Sharon School | 18 | 8 | CREATE | — |
| 16 | Thumbelina Day Care Centre | 10 | 5 | CREATE | — |
| 17 | Cicina Nursery and Primary School | 75 | 8 | CREATE | — |
| 18 | Muse Advent Academy | 5 | 0 | CREATE | — |
| 19 | Kinsosha General Contractors Limited | 20 | 4 | CREATE | — |
| 20 | Yamike Kids center nursery/Primary school | 9 | 0 | CREATE | — |
| 21 | Nima Private School | 7 | 0 | CREATE | — |
| 22 | Shalome School of Health and Business Studies | 14 | 0 | CREATE | — |
| 23 | Factory One Design Limited | 7 | 5 | CREATE | — |
| 24 | Little Sweethearts Childcare and Education Center | 23 | 15 | CREATE | — |
| 26 | Mango Grove School | 24 | 2 | CREATE | — |
| 27 | Tots and Toddlers School | 18 | 10 | CREATE | — |
| 28 | Shah Motors Limited | 15 | 9 | CREATE | — |
| 29 | Petku academy Limited | 42 | 8 | CREATE | — |
| 30 | Just Kids Christian Academy | 26 | 0 | CREATE | — |
| 32 | Celines Academy | 19 | 5 | CREATE | — |
| 33 | Multisensory International School | 10 | 3 | CREATE | — |
| 34 | Sophies Christian Academy | 22 | 3 | CREATE | — |
| 35 | Barnabas Daycare And Nursery | 17 | 2 | CREATE | — |
| 38 | Musa Trust School | 7 | 6 | CREATE | — |
| 39 | Andaxin Int Logostics Zambia | 2 | 2 | CREATE | — |

Unused duplicate rows (25, 31, 37) have zero customers and are skipped.

## Skipped placeholders

| ID | Name | Classification | Customers |
|---:|---|---|---:|
| 8 | GRZ | GOVERNMENT_PRODUCT_PLACEHOLDER | 1,060 |
| 36 | Marketize Loans | MARKETEER_PRODUCT_PLACEHOLDER | 35 |
| 2 | Vendor | CHARACTER_PRODUCT_PLACEHOLDER | 1 |
| 4 | Character-based-1 | CHARACTER_PRODUCT_PLACEHOLDER | 4 |
| 5 | Character-Based | CHARACTER_PRODUCT_PLACEHOLDER | 4 |
| 6 | Character-based-2 | CHARACTER_PRODUCT_PLACEHOLDER | 52 |
| 7 | Character-based-3 | CHARACTER_PRODUCT_PLACEHOLDER | 254 |

## Manual review clients

| ID | Name | Customers | Note |
|---:|---|---:|---|
| 1 | Finedge Stuff | 2 | Internal/test — no auto company |
| 9 | Finedge Test | 2 | Internal/test — no auto company |

## Customer company buckets (dry-run, n=1,936)

| Bucket | Count |
|---|---:|
| MOU_COMPANY_LINKED | 28 |
| GOVERNMENT_INTENTIONAL_NO_COMPANY | 1,060 |
| CHARACTER_INTENTIONAL_NO_COMPANY | 315 |
| MARKETEER_INTENTIONAL_NO_COMPANY | 35 |
| COMPANY_MAPPING_PENDING | 494 |
| MANUAL_REVIEW | 4 |
| **Total** | **1,936** |

`COMPANY_MAPPING_PENDING` (494) = MOU salary customers whose employer company map does not exist yet. Resolves after reference-data `--promote` creates the 25 remaining MOU companies.

Government (1,060), Marketeer (35), and Character (315) customers are **intentional no-company** — not mapping pending.

## Obsolete pilot company maps

Superseded entity maps (must not link customers):

- Client 36 → company 9 (marketeer)
- Client 6 → company 5 (character agent)
- Client 7 → company 6 (character agent)
- Client 2 → company 4 (Vendor character bucket)

Active MOU map retained: client 11 → company 8 (Starlabs).

## Implementation

- `App\Migration\Phases\Support\LegacyClientClassifier`
- `ReferenceDataMigrator::migrateCompanies()` — only MOU creates/matches
- `CustomerMigrator::classifyCompanyCoverage()` — revised buckets
- `PrePromotionAuditService` — audit uses same classifier

## Dry-run commands (no promote)

```bash
php artisan migration:reference-data --dry-run
php artisan migration:customers --dry-run
php artisan migration:audit
```

Outputs: `docs/data-migration/tools/m2-reference-dry-run.json`, `m2-customers-dry-run.json`, `m2-pre-promotion-audit.json`
