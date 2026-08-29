# M1 — Pilot Migration Report

**Run IDs:** #5 / #6 (idempotent)  
**Date:** 2026-08-22  
**Scope:** 20-loan M0 pilot set → **11 active loans imported**

---

## Pilot loan scope

| Category | Loan IDs | Imported? |
|----------|----------|:---------:|
| Simple MOU (301) | 2584, 2631, 2882 | Yes |
| MOU multi (301) | 7803, 14227, 14589, 16171 | Yes |
| Marketeer (301) | 18064, 18237 | Yes |
| Difficult MOU (301) | 17753, 18369 | Yes |
| Settled support | 187, 230, 232, 450, 476, 477, 1, 2, 1678 | **No** (read for repayments only) |

---

## Import summary

| Metric | Value |
|--------|------:|
| Customers staged/imported | 13 |
| Companies staged/imported | 10 |
| Active loans imported | 11 |
| Repayments staged | 310 |
| Repayments promoted (A_DIRECT) | 75 |
| Ambiguous repayments | 0 |

---

## Balance reconciliation (snapshot)

Tolerance: **ZMW 0.01**

| Legacy loan ID | Legacy effective | Target outstanding | Variance | Pass |
|---------------:|-----------------:|-------------------:|---------:|:----:|
| 2584 | 2,662.75 | 2,662.75 | 0.00 | ✓ |
| 2631 | 1,356.61 | 1,356.61 | 0.00 | ✓ |
| 2882 | 1,143.11 | 1,143.11 | 0.00 | ✓ |
| 7803 | 177.00 | 177.00 | 0.00 | ✓ |
| 14227 | 1,100.02 | 1,100.02 | 0.00 | ✓ |
| 14589 | 13,560.00 | 13,560.00 | 0.00 | ✓ |
| 16171 | 2,130.20 | 2,130.20 | 0.00 | ✓ |
| 17753 | 21,826.99 | 21,826.99 | 0.00 | ✓ |
| 18064 | 1,400.00 | 1,400.00 | 0.00 | ✓ |
| 18237 | 1,300.00 | 1,300.00 | 0.00 | ✓ |
| 18369 | 32,400.00 | 32,400.00 | 0.00 | ✓ |

**Largest variance:** ZMW 0.0035 (loan 2882 floating-point artefact — within tolerance)

---

## Customer exposure reconciliation

| user_id | Legacy Σ effective | Target Σ outstanding | Variance | loans_accounts (info) |
|--------:|-------------------:|---------------------:|---------:|----------------------:|
| 374 | 2,662.75 | 2,662.75 | 0 | 1,853 |
| 392 | 1,356.61 | 1,356.61 | 0 | 312 |
| 491 | 1,143.11 | 1,143.11 | 0 | 566 |
| 596 | 177.00 | 177.00 | 0 | 1,837 |
| 941 | 16,790.22 | 16,790.22 | 0 | 21,492 |
| 916 | 54,226.99 | 54,226.99 | 0 | 32,400 |
| 1997 | 1,400.00 | 1,400.00 | 0 | 1,400 |
| 1999 | 1,300.00 | 1,300.00 | 0 | 1,300 |

`loans_accounts.balance` shown for information only — not used as migration truth.

---

## Repayment attribution (pilot scope)

| Class | Count |
|-------|------:|
| A_DIRECT | 110 |
| B_RECONSTRUCTED | 200 |
| C_AMBIGUOUS | 0 |
| D_MANUAL | 0 |

B_RECONSTRUCTED repayments are **staged** but not promoted in M1 pilot. Full replay engine required before bulk import.

---

## Bank / wallet (pilot customers)

| Check | Result |
|-------|--------|
| Bank accounts in legacy | NOT_PRESENT (all pilot customers) |
| Wallets from phone | PASS (13/13) |
| Wallet provider inferred | MEDIUM confidence |

---

## Idempotency

Second `--promote --reconcile` run produced identical loan counts and reconciliation passes. `updateOrCreate` keys:

- Companies: `code = LEG-{legacy_client_id}`
- Customers: `email` (or generated migration email)
- Loans: `loan_number = LEG-{legacy_loan_id}`
- Repayments: `external_reference = LEG-R-{legacy_repayment_id}`

---

## Commands to reproduce

```bash
php artisan migrate --path=database/migrations/2026_08_22_100000_create_migration_staging_tables.php
php artisan migration:m1-pilot --promote --reconcile
```

---

## Pilot verdict

**PASS** — All 11 active pilot loans reconcile to ≤ ZMW 0.01.

**Next:** Build B_RECONSTRUCTED repayment replay before full 752-loan import.
