# M1 — Active Migration Exceptions

**Date:** 2026-08-22

---

## Full active portfolio (752 loans) — blockers & conditions

| Exception | Count | Class | M1 action |
|-----------|------:|-------|-----------|
| B_RECONSTRUCTED repayments | 2,600 replayed / 4,673 D_MANUAL | REPLAY | D_MANUAL = no eligible loan at payment time or invalid attribution |
| C_AMBIGUOUS MOU repayments | 2 | MANUAL REVIEW | Documented in M1-C-AMBIGUOUS-CASES.md |
| A_DIRECT repayments | 6,541 | AUTO | 12 invalid affected_loan_ids demoted to replay fallback |
| Active customers without bank | 716 | NOT_PRESENT | Import wallet from phone; bank optional |
| Multi-active-loan customers | 28 | MANUAL REVIEW | 7 from M0 + portfolio rules |
| ZICB treasury bank unmapped | 1 | MANUAL_REVIEW | Add FI or manual map |
| Kazang treasury wallet | 1 | MANUAL_REVIEW | Treasury only; not customer provider |
| Marketeer product (MARK-001) | — | AUTO | Created by pilot importer if missing |

---

## Bank / wallet exceptions

| Exception | Count | Class |
|-----------|------:|-------|
| Customer bank NOT_PRESENT | 716 | NOT_PRESENT |
| Invalid bank records | 0 | — |
| Invalid wallet numbers | 0 | — |
| Duplicate wallet numbers | 0 | — |
| BANK_ACCOUNT_MULTIPLE_TARGET_LIMITATION | N/A | Design note (revamp allows 1 method) |

---

## Pilot exceptions

| Item | Status |
|------|--------|
| Ambiguous MOU in pilot | 0 |
| Balance variance failures | 0 |
| Missing company mapping | 0 |
| Missing product mapping | 0 |
| Missing wallet | 0 |

---

## Reconciliation exceptions (informational)

Pilot customers where `loans_accounts.balance` ≠ Σ per-loan effective (expected — customer aggregate):

| user_id | Σ per-loan effective | loans_accounts |
|--------:|---------------------:|---------------:|
| 916 | 54,226.99 | 32,400.00 |
| 941 | 16,790.22 | 21,491.79 |
| 596 | 177.00 | 1,837.08 |

These are **not migration failures** — aggregate account must not drive per-loan balances.

---

## GO / NO-GO

### 20-loan pilot

| Criterion | Status |
|-----------|:------:|
| Deterministic loans reconcile ≤ 0.01 | ✓ |
| No repayments lost (staged) | ✓ |
| No duplicate promotes (idempotent) | ✓ |
| Ambiguous MOU isolated | ✓ (0 in pilot) |
| Bank/wallet mapped or NOT_PRESENT | ✓ |
| Identity / company / product mapping | ✓ |

**Verdict: PILOT GO**

### Full 752-loan active portfolio (M1.1 replay — 2026-08-22)

| Criterion | Status |
|-----------|:------:|
| B_RECONSTRUCTED replay engine | ✓ Built |
| Per-loan balance reconcile ≤ 0.01 | ✓ 750/752 promotable |
| C_AMBIGUOUS queue (2 repayments) | ✓ Isolated — see M1-C-AMBIGUOUS-CASES.md |
| Repayment conservation | ✓ |
| Allocation conservation (A/B) | ✓ |
| Bulk promote all 752 | ✗ **Not performed** (by design) |

**Verdict: FULL ACTIVE PORTFOLIO GO** for 750 auto-promotable loans; 2 loans MANUAL_REVIEW (user 1835).

---

### Full 752-loan active portfolio (M1 pre-replay — superseded)

| Criterion | Status |
|-----------|:------:|
| B_RECONSTRUCTED engine | ✗ Not built |
| C_AMBIGUOUS queue (2 repayments) | ✗ Not reviewed |
| Ledger replay vs snapshot | ✗ M1 uses snapshot only |
| Bulk idempotency tested | ✗ Pilot only |

**Verdict: FULL PORTFOLIO NO-GO** (until B_RECONSTRUCTED replay + ambiguous queue)

---

## Secondary migration (M2/M3 — not started)

Documented for later:

- 6,892 settled loans
- Cancelled status 305 (9,486 loans)
- Historical statements
- Full repayment history beyond active balance needs
