# M2 — Repayment Migration

## Scope

Repayments for **active loan portfolio** only, using M1.1 replay engine.

## Promotion rules

| Class | Action |
|-------|--------|
| A_DIRECT | Promote allocation |
| B_RECONSTRUCTED | Promote allocation |
| C_AMBIGUOUS | Exception only — no allocation |
| D_MANUAL | Subclassified D1–D4 — no fake allocations |

### D_MANUAL subclasses

- **D1** HISTORICAL_SUPPORT_ONLY — no eligible loan at payment time
- **D2** CURRENT_BALANCE_BRIDGED — bridged via migration adjustment
- **D3** REQUIRES_REVIEW
- **D4** BLOCKING

## Prerequisites

- Target active loans promoted (`migration:active-loans --promote`)
- Loan entity maps exist (for `--promote`)

Dry-run replays without requiring loan maps.

## Dry-run result (sample)

| Metric | Value |
|--------|------:|
| A_DIRECT | 6,541 |
| B_RECONSTRUCTED | 2,600 |
| C_AMBIGUOUS (excluded) | 2 |
| D_MANUAL (excluded) | 4,673 |
| Would promote | 9,141 |

Output: `docs/data-migration/tools/m2-repayments-dry-run.json`
