# M0 — Migration Exception Summary

**Database:** `finedge`  
**Generated:** 2026-08-22  
**Source:** Read-only profiling (`m0-profile-legacy.php`)

Classification key:

| Class | Meaning |
|-------|---------|
| **AUTO-FIXABLE** | Safe deterministic transform during ETL |
| **REQUIRES RULE** | Needs documented business rule |
| **MANUAL REVIEW** | Human decision before import |
| **BLOCKING** | Must resolve before any import |

---

## Summary table

| Exception | Count | Class | Action |
|-----------|------:|-------|--------|
| Settled (300) with positive effective balance | 1,117 | REQUIRES RULE | Floor at 0 or reopen loan; likely negative `current_loan_amount` artefact |
| repaid_amount > loan_amount | 1,661 | MANUAL REVIEW | Cap repaid at obligation or investigate overpayments |
| Repayment before customer's first loan | 11 | MANUAL REVIEW | Data entry timing; verify user_id |
| Duplicate `client_transaction_reference` | 5 | MANUAL REVIEW | Dedup or mark idempotent |
| Successful repayment with zero/negative amount | 4 | MANUAL REVIEW | Exclude or fix amount |
| Invalid `affected_loan_ids` JSON | 0 | — | — |
| `affected_loan_ids` refs nonexistent loan | 0 | — | — |
| Loans missing users | 0 | BLOCKING | — |
| Customers missing users | 0 | — | — |
| Customers without clients | 0 | REQUIRES RULE | — |
| Repayments without users | 0 | BLOCKING | — |
| loans_accounts without users | 0 | BLOCKING | — |
| Users with >1 loans_accounts row | 0 | — | — |
| Loans missing client | 0 | REQUIRES RULE | — |
| Active (301) with zero effective balance | low | REQUIRES RULE | Auto-settle or exclude |
| Negative effective balance | 0 | — | — |

---

## Identity exceptions

### Duplicate keys (not auto-errors)

| Field | Duplicate groups | Samples |
|-------|-----------------:|---------|
| customers.nrc | 2 | IDs 115/116; 5/8 |
| users.nrc | 6 | Shared across records |
| users.emp_number | 56 | Employer bulk import |
| users.phone_number | 6 | Small set |

**Class:** REQUIRES RULE — dedup policy (merge vs keep separate).

---

## Repayment attribution exceptions

### Pre-2025 unattributed repayments

| Period | Successful | Without affected_loan_ids |
|--------|----------:|-------------------------:|
| 2021–2024 | 14,682 | 14,682 (100%) |
| 2025 | 7,865 | 522 (6.64%) |
| 2026 | 1,969 | 0 |

**Class:** REQUIRES RULE — reconstruct via product allocation.

### Ambiguous MOU (highest financial priority)

| Metric | Value |
|--------|------:|
| Customers | 7 |
| Repayments | 102 |
| Value | ZMW 157,594.66 |

**Class:** MANUAL REVIEW — cannot safely auto-split across concurrent active MOU loans.

Affected users: 916, 73, 941, 1835, 596, 1662, 336.

---

## Balance exceptions

### loans_accounts vs per-loan effective (active)

| Variance bucket | Customers |
|-----------------|----------:|
| > ZMW 100 | 361 |
| Total absolute variance | ZMW 967,750.13 |

**Class:** REQUIRES RULE — use per-loan effective as truth; flag account drift.

### Balance adjustments

| Metric | Value |
|--------|------:|
| Rows | 146 |
| Customers | 129 |
| Net adjustment | ZMW 23,862.28 |

**Class:** MANUAL REVIEW / REQUIRES RULE — no loan_id on adjustments.

---

## Loan status exceptions

| Status | Count | Migration handling |
|--------|------:|-------------------|
| 305 | 9,486 | Exclude from active book (cancelled/rejected) |
| 300 | 6,892 | Import as settled |
| 301 | 752 | Import as active — verify balance |
| 308 | 237 | Map to revamp cancelled/rejected |

---

## Product signal exceptions

| Issue | Impact |
|-------|--------|
| `salary_based` + `gvnt_loan` overlap | 8,152 loans flagged gvnt only |
| Active loans under salary_based client without salary_based flag | e.g. user 916 |
| Product type `marketize_based` vs code `marketize` | Naming mapping needed |

**Class:** REQUIRES RULE — product classifier function for ETL.

---

## Refinance exceptions

| Check | Count |
|-------|------:|
| loan_refinances rows | 0 |
| Broken chains | 0 |

**No exceptions.**

---

## Estimated migration exception population

| Tier | Est. records | Est. financial exposure |
|------|-------------:|------------------------:|
| Auto-import clean | ~85% loans, ~38% repayments by count | ~35% repayment value direct |
| Rule-based reconstruction | ~62% repayments | ~64% repayment value |
| Manual review queue | 7 customers, 102 repayments, 146 adjustments, ~1,661 over-repaid | ZMW 157k + adjustment net + review settled drift |
| Blocking | 0 | — |

---

## Priority order for manual review

1. **7 ambiguous MOU customers** (102 repayments, ZMW 157k)
2. **361 accounts** with variance > ZMW 100 (focus top 20 by variance)
3. **1,661 over-repaid loans**
4. **146 balance adjustments**
5. **Duplicate identity keys** (policy decision)
