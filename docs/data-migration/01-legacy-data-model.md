# 01 — Legacy Data Model Summary

**Source:** Code analysis + M0 profiling of `finedge` database  
**Updated:** 2026-08-22

This document materializes the prior Ask-mode analysis into the repo.

---

## Core entities

```
clients (employers/companies)
    └── customers (biodata + employment, linked to user_id)
            └── users (auth + phone/NRC/emp_number)
                    └── loans (individual loan facility — loans.id)
                    └── repayments (customer-level payment events)
                    └── loans_accounts (ONE row per customer — aggregate balance)
```

---

## Key tables

| Table | Role | Migration priority |
|-------|------|-------------------|
| `clients` | Employer/company; `product_type` drives repayment routing | HIGH |
| `customers` | Customer biodata; `user_id` FK | HIGH |
| `users` | Login identity | HIGH |
| `loans` | **Individual loan facility** — primary loan grain | HIGH |
| `repayments` | Customer-level repayments; `affected_loan_ids` JSON (from 2025) | CRITICAL |
| `loans_accounts` | Customer aggregate balance — **NOT per-loan** | REFERENCE ONLY |
| `marketize_loan_schedules` | Weekly schedule per marketize loan | MEDIUM |
| `loan_account_balance_adjustments` | Manual account corrections | MEDIUM |
| `loan_refinances` | Refinance chains | LOW (empty) |
| `transactions` | Disbursement/ledger transactions | MEDIUM |

---

## loans.id semantics

**Confirmed by M0:** `loans.id` = one loan facility. Customers commonly have many loans over time (71% have >1 historical loan). Active multi-loan customers: 28 (1.5%).

---

## Repayment model

- Repayments are stored at **customer level** (`repayments.user_id`)
- `affected_loan_ids` added in application code (~2025); first populated in data: **2025-01-01**
- Legacy allocation (`LoanRepaymentController`):
  - **salary_based / MOU:** `.first()` active loan — ambiguous if multiple active
  - **character_based:** due-date waterfall across active loans
  - **marketize:** weekly schedule distribution

Revamp requires every repayment attributable via `loan_repayments` pivot.

---

## Balance model

`LoanBalanceService::getEffectiveOutstandingBalance()`:

| Loan type | Balance source |
|-----------|----------------|
| Accrual (MOU/gvnt: `salary_based` or `gvnt_loan`) | `current_loan_amount` |
| Fixed (character/marketize) | `current_loan_amount` if set, else `loan_amount - repaid_amount` |

`loans_accounts.balance` = customer aggregate; M0 shows 19.3% of accounts drift > ZMW 100 from sum of active per-loan balances.

---

## Product signals

| Signal | Values (M0) |
|--------|-------------|
| `clients.product_type` | salary_based (12,008 loans), character_based (5,293), marketize_based (71) |
| `loans.salary_based` | 9,465 loans |
| `loans.gvnt_loan` | 8,152 loans |

Use combined classifier — flags and product_type disagree on some active loans.

---

## Status codes (observed)

| Code | M0 count | Typical meaning |
|------|----------:|-----------------|
| 301 | 752 | Active |
| 300 | 6,892 | Settled |
| 305 | 9,486 | Cancelled/rejected |
| 308 | 237 | Other inactive |

---

## Related docs

- [M0-DATABASE-PROFILING.md](./M0-DATABASE-PROFILING.md)
- [MIGRATION-READINESS-REPORT.md](./MIGRATION-READINESS-REPORT.md)
