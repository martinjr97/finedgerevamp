# M0 — Pilot Loan Set (20 loans)

**Purpose:** Representative loans for M1 pilot ETL and balance verification.  
**Selection date:** 2026-08-22  
**Source:** Automated selection from `finedge` + manual curation for gaps.

---

## Selection criteria coverage

| Category | Target | Selected |
|----------|:------:|:--------:|
| Simple MOU (single active) | 3 | 3 |
| MOU with repayments | 3 | 3 |
| MOU multi-loan customer | 2 | 4 loans (2 customers) |
| Character loans | 3 | 5 |
| Character multi-loan | 2 | (included in character set) |
| Marketize | 2 | 2 |
| Settled | 2 | 2 |
| Refinanced | 1 | 0 — **no refinance data in DB** |
| Account adjustment | 1 | 1 |
| Difficult / ambiguous | 1 | 1 (user 916) |

**Total: 20 loans**

---

## Pilot loan table

| # | Legacy loan ID | user_id | Category | Product | Status | Principal (ZMW) | Effective outstanding | Repayments | With affected | Confidence |
|--:|---------------:|--------:|----------|---------|:------:|----------------:|----------------------:|-----------:|--------------:|:----------:|
| 1 | 2584 | 374 | simple_mou | salary_based | 301 | 2,283 | 2,662.75 | 20 | 8 | HIGH |
| 2 | 2631 | 392 | simple_mou | salary_based | 301 | 2,427 | 1,356.61 | 16 | 2 | HIGH |
| 3 | 2882 | 491 | simple_mou | salary_based | 301 | 2,217 | 1,143.11 | 11 | 1 | HIGH |
| 4 | 187 | 52 | mou_repayments | salary_based | 300 | 114 | 0 | 8 | 0 | HIGH |
| 5 | 230 | 52 | mou_repayments | salary_based | 300 | 224 | 0 | 8 | 0 | HIGH |
| 6 | 232 | 52 | mou_repayments | salary_based | 300 | 335.25 | 0 | 8 | 0 | HIGH |
| 7 | 7803 | 596 | mou_multi | salary_based | 301 | 3,976 | 177 | 31 | 14 | LOW |
| 8 | 14227 | 941 | mou_multi | salary_based | 301 | 13,754 | 1,100.02 | 21 | 6 | LOW |
| 9 | 14589 | 941 | mou_multi | salary_based | 301 | 11,412 | 13,560 | 21 | 6 | LOW |
| 10 | 16171 | 941 | mou_multi | salary_based | 301 | 1,396 | 2,130.20 | 21 | 6 | LOW |
| 11 | 450 | 10 | character | character_based | 300 | 58.75 | 0 | 53 | 21 | HIGH |
| 12 | 476 | 14 | character | character_based | 300 | 97.68 | 0 | 4 | 0 | HIGH |
| 13 | 477 | 14 | character | character_based | 300 | 12.21 | 0 | 4 | 0 | HIGH |
| 14 | 18064 | 1997 | marketize | marketize_based | 301 | 2,800 | 1,400 | 16 | 16 | HIGH |
| 15 | 18237 | 1997 | marketize | marketize_based | 301 | 2,100 | 1,300 | 16 | 16 | HIGH |
| 16 | 1 | 12 | settled | salary_based | 300 | 1 | 0 | 1 | 0 | HIGH |
| 17 | 2 | 14 | settled | salary_based | 300 | 1 | 0 | 4 | 0 | HIGH |
| 18 | 1678 | 48 | adjustment | character_based | 300 | 5,950 | 0 | 86 | 14 | MANUAL REVIEW |
| 19 | 17753 | 916 | difficult | salary_based | 301 | 20,276 | 21,827 | 27 | 14 | MANUAL REVIEW |
| 20 | 18369 | 916 | difficult | salary_based | 301 | 32,400 | 32,400 | 27 | 14 | MANUAL REVIEW |

---

## Confidence definitions

| Level | Meaning |
|-------|---------|
| **HIGH** | Single allocation path; reconstruction matches stored balances |
| **MEDIUM** | Reconstructible with rules; minor drift possible |
| **LOW** | Multi-active MOU; `.first()` ambiguity |
| **MANUAL REVIEW** | Adjustments, inconsistent flags, or high-value ambiguity |

---

## Recommended pilot order

1. **Marketize** (18064, 18237) — smallest book; deterministic schedules
2. **Simple MOU** (2584, 2631, 2882) — single loan, prove accrual balance
3. **Settled MOU** (187, 230, 232) — historical reconstruction without affected_loan_ids
4. **Character** (450, 476, 477) — waterfall allocation
5. **MOU multi** (7803, 14227, 14589, 16171) — prove ambiguity handling
6. **Adjustment** (1678) — adjustment + repayment interaction
7. **Difficult** (17753, 18369) — user 916 dual-active edge case

---

## Success criteria (M1)

For each pilot loan after import to revamp staging:

- [ ] Effective outstanding matches legacy `LoanBalanceService` within ZMW 0.01
- [ ] Sum of `loan_repayments` equals legacy `repaid_amount` per loan (or documented exception)
- [ ] Customer statement closing balance equals sum of active loan outstanding
- [ ] Ambiguous cases flagged in review queue, not silently assigned

---

## Notes

- **Refinance:** `loan_refinances` table is empty — substitute with settled MOU loan 1/2 until refinance data exists.
- **User 916:** Has 2 concurrent active loans under salary_based client without `salary_based=1` flags — tests product classifier + MOU `.first()` rule.
- **User 941:** Three active MOU loans — primary LOW-confidence multi-loan case.
