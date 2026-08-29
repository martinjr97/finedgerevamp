# M1.1 Replay Reconciliation — Full Active Portfolio Dry-Run

**Run:** `#11` (2026-08-22)  
**Scope:** 752 active loans, 716 customers  
**Verdict:** **GO** for automatic promotion of 750 loans; **2 loans** require manual review (C_AMBIGUOUS).

## Loan-level reconciliation

| Status | Count |
|--------|------:|
| PASS | 107 |
| PASS_WITH_MIGRATION_ADJUSTMENT | 643 |
| MANUAL_REVIEW | 2 |
| FAIL | 0 |

## Product-level financial totals

| Product | Loans | Legacy outstanding | Reconstructed | Variance | PASS | PASS+Adj | Manual | FAIL |
|---------|------:|-------------------:|--------------:|---------:|-----:|---------:|-------:|-----:|
| Government | 429 | 1,694,921.15 | 1,694,921.19 | 0.11 | 1 | 428 | 0 | 0 |
| MOU | 157 | 928,747.16 | 928,747.21 | 0.04 | 6 | 149 | 2 | 0 |
| Character | 142 | 1,609,296.02 | 1,609,296.02 | 0.00 | 82 | 60 | 0 | 0 |
| Marketeer | 24 | 32,625.00 | 32,625.00 | 0.00 | 18 | 6 | 0 | 0 |

## Customer-level reconciliation

| Status | Count |
|--------|------:|
| PASS | 715 |
| MANUAL_REVIEW | 1 |
| FAIL | 0 |

Customer 1835 (Barnabas Daycare) → MANUAL_REVIEW (2 C_AMBIGUOUS repayments).

## Repayment attribution (replay)

| Class | Count |
|-------|------:|
| A_DIRECT | 6,541 |
| B_RECONSTRUCTED | 2,600 |
| C_AMBIGUOUS | 2 |
| D_MANUAL | 4,673 |

## Conservation

| Check | Result |
|-------|--------|
| Source repayment total | ZMW 14,498,236.88 |
| Staged repayment total | ZMW 14,498,236.88 |
| Repayment conservation | **PASS** |
| Allocation conservation (A/B) | **PASS** |

## Largest deterministic variance

Loan **3248** (Government): ZMW **0.01** — PASS_WITH_MIGRATION_ADJUSTMENT.

## Promotion readiness

| Bucket | Active loans |
|--------|-------------:|
| PROMOTABLE | 750 |
| MANUAL_REVIEW | 2 |
| BLOCKED | 0 |

## Account adjustments

146 legacy `loan_account_balance_adjustments` exist portfolio-wide. Adjustments in active-customer scope are **account-level** (no `loan_id`). Flag: `ACCOUNT_ADJUSTMENT_UNALLOCATED`. Reconciliation anchor remains per-loan effective outstanding, not `loans_accounts.balance`.

## Commands to reproduce

```bash
php artisan migration:m1-replay --dry-run --output=docs/data-migration/tools/m1-replay-output.json
php artisan migration:m1-reconcile --run=10
```
