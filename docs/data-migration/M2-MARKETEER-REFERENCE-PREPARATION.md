# M2 — Marketeer Reference Preparation

**Date:** 2026-08-22

---

## Promotion executed

```bash
php artisan migration:reference-data --only=marketeer --promote
```

| Field | Value |
|-------|-------|
| Run UUID | `0447e5dc-df50-4c7d-8e51-ae82258f36e4` |
| migration_run_id | 25 |

**Scope:** Marketeer reference data only. No customers, loans, or repayments promoted.

---

## Results

| Entity | Legacy | Target | Action | Target id |
|--------|--------|--------|--------|----------:|
| MARK-001 | MARK-001 | Marketeer Loan | MATCHED (pre-existing) | 8 |
| MRKT-LEGACY | — | Legacy Marketeer Markets | CREATED | 4 |
| Lilanda | market 1 | Lilanda Market (`MRKT-LEG-1`) | CREATED | 1 |
| Mwamba Luchembe | market 2 | Mwamba Luchembe Market (`MRKT-LEG-2`) | CREATED | 2 |

---

## Post-promote dry-run (idempotency)

```bash
php artisan migration:reference-data --only=marketeer --dry-run
```

```json
"marketeer": {
  "groups": { "MATCHED_EXISTING": 1, "WOULD_CREATE": 0 },
  "markets": { "MATCHED_EXISTING": 2, "WOULD_CREATE": 0 }
}
```

**0 would-create** — idempotent ✓

---

## Client 36 handling

| Check | Status |
|-------|--------|
| Company migration | `SKIP_MARKETEER_PLACEHOLDER` |
| Pilot map client 36 → company 9 | `OBSOLETE_IGNORED` (metadata superseded) |
| Target company 9 | **Not deleted** — may have other uses |
| Customer company_id for Marketeer | **Always null** |

---

## Customer dry-run (Marketeer)

| Metric | Value |
|--------|------:|
| marketeer_customers | 35 |
| marketeer_market_mapped | **35** |
| marketeer_market_pending | **0** |
| marketeer_missing_market | **0** |
| marketeer_incorrect_company_link | **0** |

All 35 resolve through MARK-001 → MRKT-LEGACY → Market (not companies).

---

## Target relationship (verified)

```text
Customer.company_id = null
Customer.customer_group_id = MRKT-LEGACY (on promote)
Customer.loan_product_id = MARK-001
MarketeerCustomerDetail.market_id = mapped Lilanda or Mwamba Luchembe
Loan.loan_product_id = MARK-001 (at loan migration)
```

---

## Active Marketeer loan precheck

24 active Marketeer loans: **no structural blocker**. Remain `blocked_missing_customer` until customer promote (expected).

Repayment replay (`MarketizeReplayStrategy`) unchanged.

---

## Safety checks passed

1. MARK-001 mapped, not overwritten ✓
2. No duplicate group created ✓
3. No duplicate markets created ✓
4. Idempotent rerun ✓
5. Client 36 excluded ✓
6. Scoped to `--only=marketeer` ✓
