# M1 C_AMBIGUOUS Cases — Active Portfolio

Two repayments for customer **1835** (Barnabas Daycare And Nursery, legacy client 35, `salary_based`) cannot be allocated without guessing. Both loans remain active; neither has `affected_loan_ids`.

## Case 1

| Field | Value |
|-------|-------|
| Legacy repayment ID | **28303** |
| Customer ID (user) | **1835** |
| Repayment date | 2025-09-30 00:00:00 |
| Amount | ZMW 5,000.00 |
| Eligible loan IDs | **16969**, **17617** |
| `affected_loan_ids` | null |
| Legacy code path | `executeMouLoanRepayment()` → would use `.first()` on status 301 (non-deterministic) |
| Classification | **C_AMBIGUOUS** |
| Rule | `salary_based_accrual_multi_active_no_attribution` |

### Balances before payment (replay state)

| Loan | Product | `current_loan_amount` | `loan_amount` | `repaid_amount` (replay) |
|------|---------|----------------------:|--------------:|-------------------------:|
| 16969 | MOU (`salary_based=1`) | 14,655 | 14,655 | 0 |
| 17617 | MOU client / non-salary flag | 10,678 | 22,678 | 0 |

### Possible allocations (not selected)

- ZMW 5,000 → loan 16969 only
- ZMW 5,000 → loan 17617 only
- Split across both (no legacy evidence for split)

### Financial impact

Until manually resolved, **loans 16969 and 17617** for user 1835 are **MANUAL_REVIEW** / not auto-promotable. Customer-level totals still reconcile because ambiguous repayments are excluded from allocations and bridged via migration adjustment on other cash flows.

---

## Case 2

| Field | Value |
|-------|-------|
| Legacy repayment ID | **28308** |
| Customer ID (user) | **1835** |
| Repayment date | 2025-11-06 12:04:47 |
| Amount | ZMW 5,000.00 |
| Eligible loan IDs | **16969**, **17617** |
| `affected_loan_ids` | null |
| Legacy code path | Same as case 1 |
| Classification | **C_AMBIGUOUS** |

### Balances before payment (replay state)

Same eligible pool as case 1 at this timestamp (both loans status 301, created before payment).

### Resolution required

Operations must determine which loan(s) received each ZMW 5,000 payment (payroll records, bank reference, or customer confirmation). Do **not** auto-allocate using legacy `.first()` ordering.

## Affected active loans

| Legacy loan ID | Status after replay |
|----------------|---------------------|
| 16969 | MANUAL_REVIEW |
| 17617 | MANUAL_REVIEW |

## GO / NO-GO impact

These 2 repayments do **not** block full-portfolio GO when their loans are excluded from bulk promotion and held for manual resolution (per M1.1 acceptance criteria).
