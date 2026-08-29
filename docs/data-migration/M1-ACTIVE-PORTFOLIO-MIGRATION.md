# M1 — Active Portfolio Migration

**Phase:** M1 (active loans only)  
**Date:** 2026-08-22  
**Legacy DB:** `finedge` (read-only)  
**Target:** `finedge-revamp`

---

## Strategy

M1 migrates the **live portfolio** only:

- **In scope:** customers with `loans.status_code = 301`, their biodata, employers, products, active loans, repayments needed for balance proof, bank/wallet destinations
- **Out of scope:** unrelated settled/historical loans (M2/M3 later)
- **Supporting reads:** historical repayments, schedules, adjustments may be read to prove active loan balances

---

## Active universe (full portfolio)

| Metric | Count |
|--------|------:|
| Active customers | **716** |
| Active loans | **752** |
| MOU active | **157** |
| Government active | **429** |
| Character active | **142** |
| Marketeer active | **24** |
| Multi-active-loan customers | **28** |
| Companies required | **26** |

Total active exposure: **ZMW 4,265,589.36** (per-loan `LoanBalanceService` logic)

---

## Product mapping

| Legacy signal | Target product | Code |
|---------------|----------------|------|
| `salary_based` / `product_type=salary_based` | MOU | MOU-001 |
| `gvnt_loan=1` | Government | GOV-001 |
| `product_type=character_based` | Character | CHAR-001 |
| `product_type=marketize_based` | Marketeer | MARK-001 |

`services` table IDs are **disbursement channels**, not loan products.

---

## Repayment attribution (752-loan scope)

| Class | Count | Meaning |
|-------|------:|---------|
| A_DIRECT | 6,553 | `affected_loan_ids` populated with amounts |
| B_RECONSTRUCTED | 7,261 | Rule-based replay required |
| C_AMBIGUOUS | 2 | Multi-active MOU without attribution — **manual queue** |
| D_MANUAL | 0 | Failed/non-success |

---

## Migration infrastructure

### Staging tables

Created by migration `2026_08_22_100000_create_migration_staging_tables.php`:

- `migration_runs`
- `migration_companies`
- `migration_customers`
- `migration_loans`
- `migration_repayments`
- `migration_financial_institutions`
- `migration_bank_accounts`
- `migration_wallet_providers`
- `migration_wallets`

### Commands

```bash
# Read-only analysis (legacy DB)
php artisan migration:m1-analyze
php artisan migration:m1-analyze --pilot

# Pilot staging (no promote)
php artisan migration:m1-pilot

# Pilot import + snapshot reconciliation
php artisan migration:m1-pilot --promote --reconcile
```

### Code location

All migration logic is isolated under `app/Migration/` — no changes to normal business services.

---

## Pilot results (20-loan set)

| Metric | Result |
|--------|--------|
| Pilot customers | 13 |
| Active loans imported | **11** |
| Settled support-only (not imported) | 9 |
| Repayments staged | 310 |
| A_DIRECT promoted | 75 |
| Ambiguous in pilot | **0** |
| Per-loan balance reconciliation | **11/11 PASS** (≤ ZMW 0.01) |
| Customer exposure reconciliation | **8/8 PASS** |
| Idempotent re-run | **Yes** (run #5 = run #6) |

**Pilot GO:** Yes — snapshot balances reconcile.

**Full 752 GO:** No — see [M1-ACTIVE-MIGRATION-EXCEPTIONS.md](./M1-ACTIVE-MIGRATION-EXCEPTIONS.md)

---

## Balance reconciliation approach (M1)

1. Import active loan with `outstanding_balance` = legacy effective (`LoanBalanceService` rules)
2. Stage all successful repayments with A/B/C/D classification
3. Promote only **A_DIRECT** repayments with valid loan allocation in pilot
4. Compare legacy effective vs imported `outstanding_balance` (snapshot mode)
5. **Do not** use `loans_accounts.balance` as loan truth

Full ledger replay from B_RECONSTRUCTED repayments is **M1.5 / M2** work.

---

## Customer completeness (pilot)

| Check | Pilot result |
|-------|-------------|
| Biodata | PASS |
| Company | PASS (or N/A for character/marketeer) |
| Product | PASS |
| Active loan(s) | PASS (11 active) |
| Repayment history | STAGED (310 rows) |
| Bank account | NOT_PRESENT (legacy columns empty) |
| Mobile wallet | PASS (from `users.phone_number`) |
| Balance | PASS |

---

## Secondary migration (document only — not implemented)

After active portfolio sign-off (M2/M3):

- Settled loans (`status_code=300`)
- Cancelled applications (`305`)
- Historical statements
- Repayments not required for active balance proof

---

## Related docs

- [M1-BANK-WALLET-ANALYSIS.md](./M1-BANK-WALLET-ANALYSIS.md)
- [M1-PILOT-MIGRATION.md](./M1-PILOT-MIGRATION.md)
- [M1-ACTIVE-MIGRATION-EXCEPTIONS.md](./M1-ACTIVE-MIGRATION-EXCEPTIONS.md)
- [M0-DATABASE-PROFILING.md](./M0-DATABASE-PROFILING.md)
