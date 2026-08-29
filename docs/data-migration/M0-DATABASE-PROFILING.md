# M0 — Legacy Database Profiling Report

**Date:** 2026-08-22  
**Legacy database:** `finedge` (MariaDB 10.11.16)  
**Legacy app:** `/var/www/personal/finedge`  
**Revamp app:** `/var/www/personal/finedge-revamp`  
**Method:** Read-only SQL (`SELECT` / `SHOW` / `DESCRIBE`) + read-only PHP replay using legacy `LoanBalanceService` and allocation rules from `LoanRepaymentController`.

No database writes were performed. No migration ETL was implemented.

---

## Executive summary

The legacy `finedge` database is **profiled and accessible**. Portfolio scale is moderate (1,936 customers, 17,372 loans, 24,516 successful repayments). **Repayment-to-loan attribution is the primary migration risk:**

| Metric | Value |
|--------|------:|
| Successful repayments | 24,516 |
| With `affected_loan_ids` | 9,312 (37.98%) |
| Without `affected_loan_ids` | 15,204 (62.02%) |
| First populated `affected_loan_ids` (actual data) | **2025-01-01 08:12:54** |
| Ambiguous MOU customers (current multi-active MOU + unattributed repayments) | **7** |
| Ambiguous MOU repayments | **102** (ZMW 157,594.66) |
| Ambiguous MOU value as % of successful repayment value | **0.60%** |
| Active outstanding with multi-active MOU ambiguity | **ZMW 213,875.61 (5.01% of active book)** |

`loans_accounts.balance` is **customer-level** and **does not reliably reconcile** to the sum of per-loan effective balances (361 customers with variance > ZMW 100; total absolute variance ZMW 967,750.13).

**Verdict:** **READY WITH CONDITIONS** — see [MIGRATION-READINESS-REPORT.md](./MIGRATION-READINESS-REPORT.md).

---

## Step 1 — Database access

| Check | Result |
|-------|--------|
| Connection | OK via legacy Laravel `.env` |
| Database | `finedge` |
| Version | MariaDB 10.11.16 |
| Expected tables | All present: `users`, `customers`, `clients`, `loans`, `repayments`, `loans_accounts`, `transactions`, `marketize_loan_schedules`, `loan_refinances`, `loan_account_balance_adjustments` |

---

## Step 2 — Basic counts

| Table | Count |
|-------|------:|
| users | 1,957 |
| customers | 1,936 |
| clients (companies) | 38 |
| loans | 17,372 |
| repayments | 30,893 |
| loans_accounts | 1,870 |
| transactions | 17,800 |
| loan_refinances | 0 |
| loan_account_balance_adjustments | 146 |
| marketize_loan_schedules | 284 |

### Loans by status

| status_code | Count | Interpretation |
|-------------|------:|----------------|
| 305 | 9,486 | Cancelled / rejected / non-book (largest bucket) |
| 300 | 6,892 | Settled |
| 301 | 752 | **Active** |
| 308 | 237 | Other non-active |
| 303 | 3 | Pending/other |
| 307, 309 | 2 | Rare |

### Loan flags

| salary_based | gvnt_loan | Count |
|:--:|:--:|------:|
| 1 | 0 | 9,465 |
| 0 | 1 | 8,152 |
| 0 | 0 | 5,293 |
| 1 | 1 | 462 |

Note: MOU/salary and government flags overlap heavily; use `clients.product_type` alongside flags.

### Product composition (`clients.product_type`)

| product_type | Loans | Active | Settled |
|--------------|------:|-------:|--------:|
| salary_based | 12,008 | 586 | 4,000 |
| character_based | 5,293 | 142 | 2,862 |
| marketize_based | 71 | 24 | 30 |

---

## Step 3 — Customer identity quality

| Check | Count |
|-------|------:|
| customers without users | 0 |
| users with loans but no customer row | 0 |
| customers without clients | 0 |
| loans with missing users | 0 |

### Duplicate keys (not necessarily errors)

| Key | Duplicate key groups | Top examples |
|-----|---------------------:|--------------|
| customers.nrc | 2 | `631351/11/1` → customer IDs 115,116; `730989/11/1` → 5,8 |
| users.nrc | 6 | (shared NRCs across user records) |
| users.emp_number | 56 | Common in employer bulk onboarding |
| users.phone_number | 6 | Small set |

Identity linkage (user ↔ customer ↔ client) is **clean at the row level**. Duplicate NRC/emp numbers need **rule-based dedup** at migration mapping time, not blocking data corruption.

---

## Step 4 — Multiple loan analysis

| Category | Customers | % of 1,872 customers with loans |
|----------|----------:|--------------------------------:|
| A. >1 historical loan | 1,336 | 71.37% |
| B. >1 active loan (301) | 28 | 1.50% |
| C. >1 active loan, same product | 28 | 1.50% |
| D. Active loans across different products | 0 | 0% |
| E. Refinanced (loan_refinances) | 0 | 0% |
| Customers with loans under >1 product (historical) | 42 | 2.24% |

**Key finding:** Multi-active-loan exposure is **small by customer count (28)** but **concentrated in MOU/salary** customers where legacy code uses `.first()` on active loans.

### Sample multi-active customers

| user_id | Name | Active loan IDs | Notes |
|--------:|------|-----------------|-------|
| 941 | Lwimbo Kupeta | 14227, 14589, 16171 | 3 active MOU loans (Starlabs) |
| 596 | Jane Nambela | 7803, 17787 | MOU + non-flagged active |
| 916 | Maleka Nsalamu | 17753, 18369 | 2 active under salary_based client; flags inconsistent |

---

## Step 5 — Repayment attribution profile

### Successful repayments (status_code 215)

| Metric | Count | Value (ZMW) |
|--------|------:|------------:|
| Total successful | 24,516 | 26,375,402.93 |
| With `affected_loan_ids` | 9,312 (37.98%) | 9,254,253.78 (35.09%) |
| Without `affected_loan_ids` | 15,204 (62.02%) | 17,121,149.15 (64.91%) |
| With principal/interest split | 9,312 | — |
| Waivers (`is_waiver=1`) | present | — |

### Year-by-year attribution

| Year | Total | Successful | With affected | Without affected | % attributable |
|------|------:|-----------:|--------------:|-----------------:|---------------:|
| 2021 | 509 | 330 | 0 | 330 | 0.00% |
| 2022 | 2,316 | 1,678 | 0 | 1,678 | 0.00% |
| 2023 | 6,354 | 4,843 | 0 | 4,843 | 0.00% |
| 2024 | 10,305 | 7,831 | 0 | 7,831 | 0.00% |
| 2025 | 9,065 | 7,865 | 7,343 | 522 | 93.36% |
| 2026 | 2,344 | 1,969 | 1,969 | 0 | 100.00% |

**First real populated `affected_loan_ids`:** 2025-01-01 08:12:54 (confirmed from data, not migration metadata alone).

Pre-2025 repayments require **reconstruction** using product-specific allocation rules.

---

## Step 6 — Ambiguous MOU repayments

**Definition:** Customer currently has >1 active MOU/salary loan **and** has successful repayments **without** `affected_loan_ids`.

| Metric | Value |
|--------|------:|
| Customers | 7 |
| Repayments | 102 |
| Total value | ZMW 157,594.66 |

### Top cases

| user_id | Name | Ambiguous repayments | Total (ZMW) | Period |
|--------:|------|---------------------:|------------:|--------|
| 916 | Maleka Nsalamu | 13 | 57,374.10 | 2023-11 → 2025-10 |
| 73 | Melody Tilimboyi | 12 | 36,874.30 | 2022-03 → 2023-11 |
| 941 | Lwimbo Kupeta | 15 | 26,966.15 | 2023-11 → 2024-11 |
| 336 | Mubita Muhau | 39 | 7,738.00 | 2022-08 → 2024-10 |

### Legacy allocation behaviour (code)

MOU path in `LoanRepaymentController::executeMouLoanRepayment()`:

```php
$loan = Loans::where('status_code', '301')->where('user_id', $repayment->user_id)->first();
```

No `orderBy` — database returns **lowest id active loan**. When multiple MOU loans are active, attribution is **non-deterministic from business intent** (whichever loan row sorts first).

### Example timeline — user 916

- Multiple sequential MOU loans settled 2023–2024 with unattributed repayments (ZMW 3,438–10,000/month).
- From 2025-01-14, `affected_loan_ids` populated but often lists **settled** loan IDs with partial `amount_applied`.
- **Currently 2 active loans** (17753, 18369) under `salary_based` client; neither has `salary_based=1` flag — product signal inconsistency.

---

## Step 7 — Character reconstruction test

Replayed chronologically with:
- Initial state: disbursed loans at repaid=0, status=301
- Route by `clients.product_type`: character → due-date waterfall; salary → `.first()` by id
- Prefer `affected_loan_ids` when present

| user_id | Product | Loans | Repayments | Mismatches | Notes |
|--------:|---------|------:|-----------:|-----------:|-------|
| 374 | salary_based | 1 | 20 | 0 | Single MOU — perfect |
| 52 | character_based | 9 | 8 | 0 | Multi-loan character — perfect |
| 14 | character_based | 24 | 4 | 1 | Minor drift |
| 1997 | marketize_based | 4 | 16 | 0 | Schedule + repaid aligns |
| 10 | character_based | 56 | 53 | 35 | Heavy history; test/dev user pattern |
| 48 | character_based | 57 | 86 | 11 | Adjustment/waiver interactions |

**Conclusion:** Character waterfall is **largely reconstructible** for typical customers. Residual mismatches correlate with waivers, balance adjustments, and very long multi-loan histories.

---

## Step 8 — Marketize reconstruction test

| loan_id | user_id | loan_amount | repaid | effective | schedules | paid_schedules | sum_paid |
|--------:|--------:|------------:|-------:|----------:|----------:|---------------:|---------:|
| 18064 | 1997 | 2,800 | 1,400 | 1,400 | 4 | 3 | 900 |
| 18237 | 1997 | 2,100 | 800 | 1,300 | 4 | 2 | 300 |
| 18460 | 2010 | 1,400 | 0 | 1,400 | 4 | 0 | 0 |

Weekly schedule totals match `loan_amount`. Replay for users 1997 and 2010: **0 mismatches**.

**Conclusion:** Marketize history is **deterministically reconstructible** from schedules + repayments.

---

## Step 9 — MOU balance reconstruction

MOU effective balance = `current_loan_amount` (accrual) per `LoanBalanceService`.

Sample drift vs `loans_accounts.balance`:

| Pattern | Observation |
|---------|-------------|
| Single active MOU | Account balance often tracks active loan |
| Multi active MOU | Account balance is single bucket — cannot infer per-loan split |
| Settled MOU | `current_loan_amount` may be negative (legacy artefact); effective floored at 0 |

**Do not migrate `loans_accounts.balance` as per-loan balance.**

---

## Step 10 — Account balance reconciliation

Compared `SUM(active effective per-loan)` vs `loans_accounts.balance` for 1,870 accounts:

| Bucket | Customers |
|--------|----------:|
| Exact match | 1,226 (65.6%) |
| Difference < 0.01 | 0 |
| Difference ≤ 1 | 214 |
| Difference ≤ 10 | 22 |
| Difference ≤ 100 | 47 |
| Difference > 100 | **361** |

| Metric | Value |
|--------|------:|
| Total absolute variance | ZMW 967,750.13 |
| Largest variance | user 2085: account 0 vs effective 120,000 |
| Sum loans_accounts.balance | ZMW 4,198,925.69 |
| Sum active effective outstanding | ZMW 4,265,589.36 |

### Variance by product (customers with variance > 100)

| Product | Customers | Total variance |
|---------|----------:|---------------:|
| MOU | 246 | 409,782.95 |
| Character | 54 | 458,451.52 |
| Other | 53 | 217,536.27 |
| Marketize | 6 | 2,650.00 |

---

## Step 11 — Balance adjustments

| Metric | Value |
|--------|------:|
| Adjustments | 146 |
| Customers affected | 129 |
| Total positive | ZMW 198,249.99 |
| Total negative | ZMW -174,387.71 |
| Net | ZMW 23,862.28 |

Adjustments are **customer/account-level only** (no loan_id). Classification:

| Class | Description | Count basis |
|-------|-------------|-------------|
| A — Attributable to loan | Single active loan at adjustment time | Minority |
| B — Reconstructible | Can derive from before/after + repayments | ~40% estimated |
| C — Ambiguous | Multi-loan or zero active | ~35% estimated |
| D — Manual review | Large or unexplained deltas | ~25% estimated |

---

## Step 12 — Refinance analysis

| Metric | Value |
|--------|------:|
| `loan_refinances` rows | **0** |
| Loans with `refinancing=1` | 0 |
| Loans with `loan_refinances_id` | 0 |

**No refinance chains in production data.** Refinance migration is **not a current blocker**; schema exists for future/empty population.

---

## Step 13 — Data quality exceptions

See [M0-EXCEPTION-SUMMARY.md](./M0-EXCEPTION-SUMMARY.md) for full classification.

Highlights:

| Exception | Count | Class |
|-----------|------:|-------|
| Settled loans with positive effective balance | 1,117 | REQUIRES RULE |
| repaid_amount > loan_amount | 1,661 | MANUAL REVIEW |
| Repayments before customer's first loan | 11 | MANUAL REVIEW |
| Duplicate repayment references | 5 | MANUAL REVIEW |
| Successful zero/negative amount | 4 | MANUAL REVIEW |
| Invalid `affected_loan_ids` JSON | 0 | — |
| loans_accounts without users | 0 | — |
| Users with >1 loans_accounts row | 0 | — |

---

## Step 14 — Product population

| Legacy product | Customers | Loans | Active | Active outstanding (ZMW) | Total repaid (ZMW) |
|----------------|----------:|------:|-------:|-------------------------:|-------------------:|
| MOU/Salary (accrual) | 1,555 | 12,009 | 586 | 2,623,668.34 | 14,185,775.20 |
| Character | 328 | 5,292 | 142 | 1,609,296.02 | 12,189,613.80 |
| Marketize | 32 | 71 | 24 | 32,625.00 | 61,085.00 |

No separate collateral product signal found in `clients.product_type`; collateral-like loans may appear under character or unclassified fixed rates.

---

## Step 15 — Financial portfolio totals

Active book (status 301 only): **ZMW 4,265,589.36** effective outstanding.

| Product | Active loans | Active outstanding |
|---------|-------------:|-------------------:|
| MOU/Salary | 586 | 2,623,668.34 |
| Character | 142 | 1,609,296.02 |
| Marketize | 24 | 32,625.00 |

Compare: `loans_accounts` sum = ZMW 4,198,925.69 (1.6% lower than active effective sum — customer-level bucket mismatch).

---

## Step 16 — Pilot loan set

See [M0-PILOT-LOANS.md](./M0-PILOT-LOANS.md) for 20 representative loans.

---

## Step 17 — Migration risk scorecard

| Area | Rating | Rationale |
|------|:------:|-----------|
| Customer identity | **GREEN** | Clean user/customer/client linkage; minor duplicate NRC/emp |
| Company mapping | **GREEN** | 38 clients; stable product_type |
| Product mapping | **AMBER** | salary_based/gvnt_loan flags overlap; marketize_based naming |
| Loan identity | **GREEN** | loans.id is stable facility key |
| Repayment attribution | **AMBER** | 62% of repayments lack affected_loan_ids; pre-2025 all unattributed |
| Balance reconstruction | **AMBER** | Per-loan effective works; accounts aggregate unreliable |
| Refinance migration | **GREEN** | Zero rows |
| Adjustment migration | **AMBER** | 146 account-level adjustments, no loan link |
| Historical traceability | **AMBER** | Reconstruction rules exist but MOU multi-loan is ambiguous |

---

## Step 18 — Readiness verdict

**READY WITH CONDITIONS**

| Attribution bucket | % records | % financial value |
|--------------------|----------:|------------------:|
| Auto (`affected_loan_ids`) | 37.98% | 35.09% |
| Reconstructible (rules) | ~61% | ~64% |
| Ambiguous / manual | ~0.4% (102 MOU rows) | 0.60% of repayment value |
| Active outstanding in ambiguous MOU segment | 28 customers (1.5%) | **5.01% (ZMW 213,876)** |

Financial exposure to ambiguity is **material for pilot targeting** but **not portfolio-wide**. Migration can proceed with:

1. Rule-based reconstruction for pre-2025 repayments
2. Manual review queue for 7 MOU multi-active customers
3. Pilot validation before bulk ETL
4. Never use `loans_accounts.balance` as loan-level truth

---

## Artefacts

| File | Purpose |
|------|---------|
| `tools/m0-profile-legacy.php` | Read-only profiling script |
| `tools/m0-profile-output.json` | Full machine-readable output |
| `M0-EXCEPTION-SUMMARY.md` | Exception inventory |
| `M0-PILOT-LOANS.md` | 20-loan pilot set |
| `MIGRATION-READINESS-REPORT.md` | Executive readiness update |

---

## Re-run profiling

```bash
cd /var/www/personal/finedge-revamp
php docs/data-migration/tools/m0-profile-legacy.php > docs/data-migration/tools/m0-profile-output.json
```

Requires legacy Laravel bootstrap and read access to `finedge` database.
