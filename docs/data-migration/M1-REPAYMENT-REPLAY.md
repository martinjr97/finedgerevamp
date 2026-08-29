# M1.1 Repayment Replay Engine

Migration-specific historical repayment replay for **active loans only** (752 loans / 716 customers). This is infrastructure under `app/Migration/` — it does not alter normal repayment processing.

## Commands

```bash
# Full active portfolio dry-run (default, non-destructive)
php artisan migration:m1-replay --dry-run

# Product-filtered dry-run
php artisan migration:m1-replay --product=character --dry-run
php artisan migration:m1-replay --product=government --dry-run
php artisan migration:m1-replay --product=mou --dry-run

# Pilot subset (11 loans)
php artisan migration:m1-replay --pilot --dry-run

# Reconciliation summary from latest run
php artisan migration:m1-reconcile
```

Output JSON: `docs/data-migration/tools/m1-replay-output.json`

## Architecture

```
app/Migration/Replay/
├── LegacyRepaymentReplayService.php   # Orchestrator + staging + reconciliation
├── Contracts/RepaymentReplayStrategy.php
├── DTOs/ReplayAllocation.php
├── DTOs/ReplayResult.php
├── Support/LegacyRepaymentContext.php
└── Strategies/
    ├── CharacterReplayStrategy.php      # due_date ASC waterfall
    ├── MarketizeReplayStrategy.php      # single active loan / schedule path
    ├── SalaryBasedClientReplayStrategy.php  # MOU + Government (legacy executeMouLoanRepayment)
    ├── MouReplayStrategy.php            # Audit alias (same accrual base)
    └── GovernmentReplayStrategy.php     # Separate class for audit trail
```

## Legacy routing (source of truth)

From `finedge/app/Http/Controllers/LoanRepaymentController.php`:

| Client type | Legacy method | Allocation rule |
|-------------|---------------|-----------------|
| Marketize customer | `executeMarketizeLoanRepayment()` | Active marketize loan / schedule |
| `salary_based` | `executeMouLoanRepayment()` | `.first()` on status 301 — **not reproduced when ambiguous** |
| Other | `executeCharacterBasedLoanRepayment()` | `due_date ASC`, balance = `loan_amount - repaid_amount` |

## Attribution classes

| Class | Meaning |
|-------|---------|
| `A_DIRECT` | Valid `affected_loan_ids` (sum equals repayment amount, same customer) |
| `B_RECONSTRUCTED` | Deterministic replay (waterfall, single eligible MOU loan, etc.) |
| `C_AMBIGUOUS` | Multiple eligible MOU loans, no reliable `affected_loan_ids` — **not guessed** |
| `D_MANUAL` | No eligible loan at payment time, invalid attribution, or partial waterfall |

## Chronological ordering

Repayments replay in order:

1. `created_at`
2. `id` (primary key tie-break)

Loan state tracks `settled_before_payment` from `settled_date` as replay progresses.

## Accrual / migration opening adjustment

MOU and Government loans accrue interest between payments. Replay applies **cash only**. The gap to legacy `LoanBalanceService` effective outstanding is stored as `migration_opening_adjustment` on `migration_loan_replay_results` — a reconciliation bridge, not a repayment or fee.

```
reconstructed = simulated_cash_outstanding + migration_opening_adjustment
```

## Staging tables

- `migration_repayment_allocations` — per-loan allocation rows (A/B only)
- `migration_loan_replay_results` — per-loan reconciliation
- `migration_customer_replay_results` — customer-level sums

## Idempotency

Each dry-run creates a new `migration_runs` row. Re-running is safe: staging rows are scoped by `migration_run_id`.

## D_MANUAL vs M1 analyze B_RECONSTRUCTED

M1 analyze classified ~7,261 repayments as `B_RECONSTRUCTED` using **current** DB status 301 at payment time. Replay uses **historical** loan state and rejects invalid `affected_loan_ids`. ~4,673 repayments are `D_MANUAL` in replay — typically payments when no loan had eligible balance at that timestamp. This is expected and more accurate for migration.
