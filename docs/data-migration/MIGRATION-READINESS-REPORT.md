# Migration Readiness Report — Legacy → Revamp

**Last updated:** 2026-08-22 (M2 pre-promotion audit)  
**Prior analysis:** Code-level forensic review (Ask mode) + M0 profiling  
**Current verdict:** Migration **PAUSED** for customer/loan/repay promote. Marketeer reference **PROMOTED**. Identity duplicates **RESOLVED**. Ready for customer dry-run review.

### Marketeer reference promoted (2026-08-22)

Run UUID: `0447e5dc-df50-4c7d-8e51-ae82258f36e4`

| Entity | Status |
|--------|--------|
| MRKT-LEGACY group | Created (id 4) |
| Lilanda (MRKT-LEG-1) | Created (id 1) |
| Mwamba Luchembe (MRKT-LEG-2) | Created (id 2) |
| Customer dry-run | 35/35 market mapped, 0 pending |

See `M2-MARKETEER-REFERENCE-PREPARATION.md`.

### Identity resolution (2026-08-22)

| Group | Resolution |
|-------|--------------|
| NRC 730989/11/1 (users 14/19) | Same person → target 7 |
| NRC 631351/11/1 (users 126/127) | Same person → primary 127, alias 126 |

See `M2-CUSTOMER-IDENTITY-RESOLUTION.md`.

### Marketeer design (2026-08-22)

| Check | Result |
|-------|--------|
| Legacy Marketeer customers | 35 (24 active) |
| Legacy markets | 2 (Lilanda, Mwamba Luchembe) |
| Client 36 placeholder | SKIP_MARKETEER_PLACEHOLDER |
| Target hierarchy | Product → Group → Market → Customer → Loan |
| Schema change | None required |
| Market migration | WOULD_CREATE 2 markets + 1 group on reference promote |

See `M2-MARKETEER-ANALYSIS.md`, `M2-MARKETEER-TARGET-DESIGN.md`, `M2-MARKETEER-MAPPING.md`.

### M2 pre-promotion audit (2026-08-22)

| Check | Result |
|-------|--------|
| Users vs customers (1957 vs 1936) | Reconciled — 21 admin/staff excluded |
| 38 legacy clients | 5 match + 29 create + 1 gov skip + 3 unused = 38 ✓ |
| Company coverage | 1060 gov + 341 char/mark + 506 pending + 29 linked = 1936 ✓ |
| Master-data overwrite | Protected (map-only) |
| Customer duplicate NRC 14/19 | **Blocker** before customer promote |
| ZICB treasury bank | MANUAL_REVIEW (non-blocking for reference promote) |

Full report: `docs/data-migration/M2-PRE-PROMOTION-AUDIT.md`

### M2 phased commands (2026-08-22)

| Phase | Command | Dry-run status |
|-------|---------|----------------|
| Reference data | `migration:reference-data` | ✓ Run |
| All customers | `migration:customers` | ✓ Run (no promote) |
| Active loans | `migration:active-loans` | ✓ Run (no promote) |
| Repayments | `migration:repayments` | ✓ Run (no promote) |
| Reconcile / status | `migration:reconcile`, `migration:status` | ✓ Available |

See `docs/data-migration/M2-PHASED-MIGRATION-COMMANDS.md`.

### M1.1 update (2026-08-22)

| Milestone | Status |
|-----------|--------|
| M0 profiling | Complete |
| M1 staging infrastructure | Complete |
| M1 pilot (11 active loans) | **PASS** — balances reconcile ≤ ZMW 0.01 |
| M1.1 replay engine (752 loans) | **PASS** — 750 promotable, 2 MANUAL_REVIEW, 0 FAIL |
| Full 752 bulk promote | **Not performed** (by design) |

---

## Verdict summary

| Phase | Status |
|-------|--------|
| Code-level analysis | Complete — READY WITH CONDITIONS |
| M0 database profiling | Complete — **confirms READY WITH CONDITIONS** |
| ETL implementation | **Not started** (by design) |

The real legacy database (`finedge`) validates the code-level findings. Migration is feasible with rule-based reconstruction and a bounded manual review queue. It is **not** safe to bulk-import repayments without proving per-loan balances in a pilot.

---

## Data-driven findings (M0)

### Portfolio scale

| Entity | Count |
|--------|------:|
| Customers | 1,936 |
| Companies (clients) | 38 |
| Loans (all statuses) | 17,372 |
| Active loans (301) | 752 |
| Settled loans (300) | 6,892 |
| Successful repayments | 24,516 |

### Repayment attribution (critical path)

| Metric | Records | Financial weight |
|--------|--------:|-----------------:|
| With `affected_loan_ids` | 9,312 (37.98%) | 35.09% of value |
| Without (needs reconstruction) | 15,204 (62.02%) | 64.91% of value |
| Ambiguous MOU (multi-active + unattributed) | 102 | ZMW 157,595 (0.60%) |

`affected_loan_ids` first appears in **live data on 2025-01-01**, not before. All 2021–2024 successful repayments require reconstruction.

### Multi-loan exposure

| Risk | Customers | Portfolio % |
|------|----------:|------------:|
| Multiple historical loans | 1,336 | 71.4% |
| Multiple **active** loans | 28 | 1.5% |
| Multiple active MOU | 10 | 0.5% |
| Ambiguous MOU repayment cases | 7 | 0.4% |

**Financial impact > record impact:** ambiguous segment = 5.01% of active outstanding (ZMW 213,876).

### Balance truth

| Source | Use in migration |
|--------|------------------|
| `LoanBalanceService` per-loan effective | **Primary truth** |
| `loans.repaid_amount` + allocation rules | Reconstruction input |
| `loans_accounts.balance` | **Reconciliation reference only** (customer-level) |

65.6% of accounts match exactly; **361 customers (19.3%)** have variance > ZMW 100.

---

## Architecture mapping (confirmed)

| Legacy | Revamp | Migration note |
|--------|--------|----------------|
| `users` + `customers` | `customers` (unified) | Map via `user_id` |
| `clients` | `companies` | 38 employers |
| `loans.id` | `loans.id` (new PK) | Preserve legacy_id |
| `repayments` (customer-level) | `repayments` + `loan_repayments` | **Core ETL challenge** |
| `loans_accounts.balance` | No direct equivalent | Do not import as loan balance |
| `marketize_loan_schedules` | Schedule tables | 284 rows — low risk |
| `loan_refinances` | Refinance links | **Empty** — skip |
| `loan_account_balance_adjustments` | Ledger adjustments | 146 rows — manual rules |

---

## Conditions before bulk ETL

### Must have

1. **Pilot migration** of 20 representative loans with balance proof ([M0-PILOT-LOANS.md](./M0-PILOT-LOANS.md))
2. **Repayment reconstruction engine** implementing:
   - MOU: `.first()` active loan by id (document ambiguity)
   - Character: due-date waterfall
   - Marketize: weekly schedule distribution
   - Post-2025: prefer `affected_loan_ids`
3. **Manual review queue** for 7 ambiguous MOU customers
4. **Exception handlers** for 1,117 settled-with-balance and 1,661 over-repaid loans

### Should have

5. Balance adjustment attribution rules (146 rows)
6. Duplicate NRC/emp_number resolution policy
7. Status 305 exclusion from active book (9,486 cancelled rows)

### Must not do

- Use `loans_accounts.balance` as per-loan outstanding
- Assume `affected_loan_ids` exists before 2025
- Auto-assign MOU repayments when multiple active loans without explicit rule + review

---

## Risk scorecard

| Area | Rating |
|------|:------:|
| Customer identity | GREEN |
| Company mapping | GREEN |
| Product mapping | AMBER |
| Loan identity | GREEN |
| Repayment attribution | AMBER |
| Balance reconstruction | AMBER |
| Refinance migration | GREEN |
| Adjustment migration | AMBER |
| Historical traceability | AMBER |

---

## Recommended next phases

| Phase | Scope |
|-------|-------|
| M1 | Pilot ETL (20 loans) + balance verification |
| M2 | Repayment reconstruction library + unit tests against legacy replay |
| M3 | Manual review tooling for ambiguous MOU + adjustments |
| M4 | Staged bulk import by product (marketize → character → MOU) |
| M5 | Reconciliation report vs legacy MI |

---

## Related documents

- [M0-DATABASE-PROFILING.md](./M0-DATABASE-PROFILING.md) — Full profiling report
- [M0-EXCEPTION-SUMMARY.md](./M0-EXCEPTION-SUMMARY.md) — Exception inventory
- [M0-PILOT-LOANS.md](./M0-PILOT-LOANS.md) — Pilot loan selection
- [01-legacy-data-model.md](./01-legacy-data-model.md) — Schema summary from code analysis
