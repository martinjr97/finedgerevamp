# M2 Pre-Promotion Consistency Audit

**Date:** 2026-08-22  
**Scope:** Read-only audit before any `--promote` command  
**Legacy DB:** `finedge` (read-only)  
**Target DB:** `finedgerevamp` (no operational writes during audit)

Machine-readable output: `docs/data-migration/tools/m2-pre-promotion-audit.json`

---

## Executive verdict

| Phase | Verdict | Notes |
|-------|---------|-------|
| **Reference data** | **READY TO PROMOTE** | 38 clients reconcile; products 4/4; ZICB treasury bank flagged MANUAL_REVIEW but non-blocking |
| **Customers** | **NOT READY** | Duplicate NRC users 14/19 both map to target customer 7 |
| **Active loans** | **BLOCKED** | 750 blocked until customer maps exist; simulation shows 750 eligible after customer promote |
| **Repayments** | **BLOCKED** | Requires loan mappings |

**Central question:** Can we account for every source record and promote only legitimate customers/companies without duplicating or overwriting corrected target data?

**Answer:** Yes, after classification fixes — with two pre-customer-promote blockers (duplicate NRC 14/19, and ZICB treasury decision for reference metadata only).

---

## 1. Customer source population (1,957 vs 1,936)

### Counts

| Metric | Count |
|--------|------:|
| Legacy `users` rows | 1,957 |
| Legacy `customers` rows | 1,936 |
| Users **with** `customers` row | 1,936 |
| Users **without** `customers` row | 21 |
| True Phase-2 migration population | **1,936** |

### Why 1,957 users but 1,936 customers?

The original M2 customer dry-run iterated the **`users`** table and reported `legacy_read=1957`. That was incorrect for migration scope.

- Every `customers.user_id` references exactly one `users.id` (1:1, zero orphan customers).
- **21 users have no `customers` row** — all are admin/staff/system accounts with **zero loans and zero repayments**.
- These 21 must **not** be inserted into revamp `customers`.

### User classification (every `users` row)

| Classification | Count | Meaning |
|----------------|------:|---------|
| `CUSTOMER_WITH_CUSTOMERS_ROW` | 1,936 | Legitimate loan-system customers |
| `ADMIN_OR_STAFF` | 20 | No customers row; Spatie staff roles |
| `SYSTEM_ACCOUNT` | 1 | User 1 "System Admin" |
| `CUSTOMER_WITHOUT_CUSTOMERS_ROW` | 0 | — |
| `INVALID_OR_ORPHAN` | 0 | — |

Staff detection uses **Spatie roles only**. Legacy `status_code=600` means **"User Is Active"** (approved customer) — it must **not** be treated as admin.

### Activity metrics (users with customers row)

| Metric | Count |
|--------|------:|
| Users who ever had a loan | 1,872 |
| Users with active loan (301) | 716 |
| Users who ever made repayment (215) | 1,788 |

---

## 2. Customer field completeness

All 1,936 customers have a valid linked `users` row.

| Bucket | Count |
|--------|------:|
| `HAS_FULL_USER_AND_CUSTOMER` | 1,936 |
| `USER_ONLY_BUT_VALID_CUSTOMER` | 0 |
| `CUSTOMER_ONLY_OR_BROKEN_RELATION` | 0 |
| `DUPLICATE_IDENTITY` | 0 (at row level; see §12 for NRC groups) |
| `MANUAL_REVIEW` | 0 (completeness; existing-map review in §11) |

No biodata is invented for user-only records.

---

## 3. Company count reconciliation (38 clients)

Prior M2 dry-run reported **5 matched + 33 would-create + 1 gov = 39** — wrong because unused clients were lumped into "would-create".

### Correct totals (invariant holds)

| Action | Count |
|--------|------:|
| `MATCH_EXISTING` | 5 |
| `CREATE` | 29 |
| `SKIP_GOVERNMENT_PLACEHOLDER` | 1 |
| `SKIP_UNUSED` | 3 |
| `MANUAL_REVIEW` | 0 |
| **Total** | **38** ✓ |

### Prior discrepancy explained

33 "would-create" included 3 clients with **zero customers** (ids 25, 31, 37). Correct split: **29 CREATE + 3 SKIP_UNUSED**.

Full per-client rows: `m2-pre-promotion-audit.json` → `client_reconciliation.rows`.

### Existing company maps (protected)

| Legacy client | Company name | Target company id |
|--------------|--------------|-------------------:|
| 2 | Vendor | 4 |
| 6 | Character-based-2 | 5 |
| 7 | Character-based-3 | 6 |
| 11 | Starlabs Limited | 8 |
| 36 | (marketize) | 9 |

---

## 4. Government company rule

### Agreed revamp design

- Generic legacy **GRZ** placeholder (client id **8**) → **`SKIP_GOVERNMENT_PLACEHOLDER`**
- Government product = **`GOV-001`** on customer default and on each government loan
- **`company_id = null`** for all 1,060 GRZ customers — intentional, not a mapping failure

### Classification

| Legacy client | Name | Classification | Customers |
|--------------|------|----------------|----------:|
| 8 | GRZ | `GENERIC_GOV_PLACEHOLDER_SKIP` | 1,060 |

No separate named ministry/agency employers were found requiring `REAL_GOV_EMPLOYER_CREATE_OR_MAP`. All MOU employers (schools, companies) are non-GRZ `salary_based` clients and will become revamp companies.

---

## 5. Government / no-company breakdown (1,060)

The prior combined bucket **"government/no-company"** is now split:

| Bucket | Count |
|--------|------:|
| Government customer — intentional no company (client 8) | 1,060 |
| Non-government missing company mapping | 0 |
| Customer whose legacy client was skipped (unused) | 0 |
| Customer with null legacy client | 0 |
| Invalid/orphan relationship | 0 |
| Other | 0 |

**All 1,060 are GRZ → intentional `company_id=null`.**

---

## 6. Customer company coverage (sums to 1,936)

| Bucket | Count | Notes |
|--------|------:|-------|
| `COMPANY_LINKED` (MOU, map exists today) | 29 | Will get `company_id` on promote |
| `GOVERNMENT_INTENTIONAL_NO_COMPANY` | 1,060 | GRZ / GOV-001 |
| `NO_COMPANY_LEGITIMATE` | 341 | CHAR-001 / MARK-001 |
| `COMPANY_MAPPING_PENDING` | 506 | MOU employers; resolved when 29 companies created |
| `MANUAL_REVIEW` | 0 | |
| **Total** | **1,936** | ✓ |

**Note:** 370 customers sit on the 5 already-mapped legacy clients, but 341 of those are character/marketize (legitimate `company_id=null`). Only **29** MOU customers on mapped clients receive `company_id` immediately.

---

## 7. Product assignment rule

| Layer | Rule |
|-------|------|
| **Loan** | `Loan.loan_product_id` is **authoritative** for financial position |
| **Customer** | `Customer.loan_product_id` required by schema; stores **legacy client default** for portal/routing |
| **Government** | Customer default `GOV-001`; loan gets `GOV-001` from `gvnt_loan` at loan migration |
| **CHAR / MARK** | `company_id=null`; per-loan product from `LegacyProductMapper` |

Do **not** force GOV-001 onto non-government customers. Do **not** force MOU product onto character loans.

---

## 8. Bank reconciliation (2 legacy treasury banks)

Legacy `banks` table (treasury/disbursement — not customer FI catalogue):

| Legacy id | Name | Code | Action | Target |
|----------:|------|------|--------|--------|
| 2 | First National Bank | FNB | `MATCH_EXISTING` | `Bank:1` Main FNB Bank Account |
| 1 | Zambia Industrial Commercial Bank | ZICB | `MANUAL_REVIEW` | No target treasury `Bank` or FI match |

ZICB does not exist in target. Promoting reference data will **not** create a duplicate FNB. ZICB requires a business decision: add treasury `Bank` record or document intentional exclusion.

---

## 9. Wallet provider reconciliation (5 legacy)

| Legacy | Code | Action | Target |
|--------|------|--------|--------|
| Kazang | — | `SKIP_TREASURY` | Operator wallet, not customer provider |
| Airtel Money | AIRTEL | `MATCH_EXISTING` | AIRTEL_MONEY |
| MTN Money | MTN | `MATCH_EXISTING` | MTN_MONEY |
| Zamtel Money | ZAMTEL | `MATCH_EXISTING` | ZAMTEL_MONEY |
| Other Wallet | OTHER | `SKIP_UNMAPPED` | Generic bucket — do not create |

Customer phone numbers are **not** used as provider proof.

---

## 10. Existing target master-data protection

Policy: **MATCH → MAP → SKIP CREATE**. Rerun only refreshes `migration_entity_maps` metadata; target business fields are never overwritten from legacy.

| Entity type | Maps | Overwrite on rerun |
|-------------|-----:|:------------------:|
| product | 4 | No |
| company | 5 | No |
| financial_institution | 1 | No |
| wallet_provider | 3+ | No |
| customer (pilot) | 14 | No |

Samples in audit JSON → `master_data_protection.samples`.

---

## 11. Existing customer matches (14)

| Legacy user | Target | Method | NRC | Emp# | Confidence |
|------------:|-------:|--------|:---:|:----:|------------|
| 10 | 4 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 12 | 6 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 14 | 7 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| **19** | **7** | **national_id** | ✓ | ✗ | **MANUAL_REVIEW** |
| 48 | 9 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 52 | 8 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 374 | 10 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 392 | 11 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 491 | 12 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 596 | 13 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 916 | 15 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 941 | 14 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 1997 | 16 | explicit_migration_mapping | ✓ | ✓ | HIGH |
| 1999 | 17 | explicit_migration_mapping | ✓ | ✓ | HIGH |

**Blocker:** Users 14 and 19 share NRC `730989/11/1` and both map to target customer 7. Resolve before `--promote`.

---

## 12. Duplicate customer exceptions

| Signal | Value | Legacy users | Target | Action |
|--------|-------|--------------|--------|--------|
| national_id | 730989/11/1 | 14, 19 | 7 | MANUAL_REVIEW |
| national_id | 631351/11/1 | 126, 127 | (none) | MANUAL_REVIEW |

No automatic merges. Phone/email alone never auto-match.

---

## 13. Reference-data dry-run (corrected)

```bash
php artisan migration:reference-data --dry-run
```

| Entity | Matched | Would create | Skipped | Manual |
|--------|--------:|-------------:|--------:|-------:|
| Products | 4 | 0 | 0 | 0 |
| Companies | 5 | 29 | 4 (1 gov + 3 unused) | 0 |
| Banks | 1 | 0 | 0 | 1 (ZICB) |
| Wallet providers | 3 | 0 | 2 | 0 |

Invariant: **legacy considered = matched + would create + intentionally skipped + manual review** for each entity type.

Output: `docs/data-migration/tools/m2-reference-dry-run.json`

---

## 14. Customer dry-run (corrected)

```bash
php artisan migration:customers --dry-run
```

| Metric | Value |
|--------|------:|
| Legacy users total | 1,957 |
| True customers identified | 1,936 |
| Excluded admin/staff/system | 21 |
| Would create | 1,922 |
| Matched existing | 14 |
| Manual review | 0 (dry-run; existing map 19 flagged in audit) |
| Company linked (MOU, map exists) | 29 |
| Government intentional no-company | 1,060 |
| Legitimate no-company (CHAR/MARK) | 341 |
| Company mapping pending | 506 |
| Reconciles | true |

Output: `docs/data-migration/tools/m2-customers-dry-run.json`

---

## 15. Active-loan precondition simulation

**Current dry-run** (no customer promote yet):

| Outcome | Count |
|---------|------:|
| Blocked — missing customer map | 750 |
| Manual review (loans 16969, 17617) | 2 |

**Simulated after customer promote** (all 1,936 customers mapped):

| Outcome | Count |
|---------|------:|
| Would promote | **750** |
| Manual review | **2** |
| Blocked — missing customer | **0** |

The 750 "blocked" loans are **not** data defects — they await Phase-2 customer entity maps.

---

## 16. Phase promotion gates

Implemented in `MigrationPromotionGate` and enforced on `--promote`:

| Phase | Gates |
|-------|-------|
| Reference data | Products mapped; client totals reconcile; no unexplained count gaps |
| Customers | True population 1,936; admin excluded; no duplicate NRC→same target; existing matches proven |
| Active loans | ≥700 customer maps; product maps; manual cohort excluded |
| Repayments | Loan maps; A/B only; C excluded; D approved |

Audit command: `php artisan migration:audit`

---

## 17–18. Command safety / no promotion

All phased command names unchanged. **No `--promote` was run** during this audit.

---

## 19. Final checklist (23 items)

| # | Item | Result |
|---|------|--------|
| 1 | Legacy users | **1,957** |
| 2 | Legacy customers | **1,936** |
| 3 | True migration population | **1,936** |
| 4 | Admin/staff/system exclusions | **21** |
| 5 | 1,957 vs 1,936 | 21 users without `customers` row (staff/system) |
| 6 | Customer create/match/manual | 1,922 / 14 / 0 dry-run (1 existing-map manual) |
| 7 | 38-client reconciliation | 5+29+1+3+0=38 ✓ |
| 8 | Government placeholder skipped | Client 8 GRZ |
| 9 | Real gov employers retained | None identified |
| 10 | Company-linked (MOU, map exists) | 29 |
| 11 | Government intentional no-company | 1,060 |
| 12 | Unresolved no-company | 0 (506 pending company **creation**, not missing) |
| 13 | Bank mapping FNB/ZICB | FNB→Bank:1; ZICB→MANUAL_REVIEW |
| 14 | Wallet mapping | 3 match, Kazang+Other skip |
| 15 | Master-data overwrite protection | Verified — map-only |
| 16 | 14 existing matches | 13 HIGH, 1 MANUAL (user 19) |
| 17 | Duplicate exceptions | 2 NRC groups |
| 18 | Reference dry-run | See §13 |
| 19 | Customer dry-run | See §14 |
| 20 | Simulated active loans | 750 promote, 2 manual |
| 21 | Tests | `MigrationPhaseTest` + promotion gate test |
| 22 | Files changed | See git diff |
| 23 | **Verdict** | **READY TO PROMOTE REFERENCE DATA**; customers **NOT READY** |

---

## Pre-promote actions required

1. **Resolve NRC duplicate** users 14/19 → single target customer 7 (remove or correct map for user 19).
2. **Decide ZICB** treasury bank: create target `Bank`, map manually, or document skip.
3. Run `migration:reference-data --promote` when approved.
4. After reference promote, re-run customer dry-run — expect `company_mapping_pending` → 0, `company_linked` → 535 (29+506).
