# Production Migration Runbook

**Status:** Template — do NOT execute until production cutover is approved.  
**Validated against:** Local rehearsal 2026-08-28 (`LOCAL-MIGRATION-REHEARSAL.md`)

---

## Before cutover

- [ ] Deploy migration code at tested git commit
- [ ] Verify `APP_ENV=production` target DB name explicitly (print name only)
- [ ] Full backup of production revamp database
- [ ] Snapshot/backup legacy database (read-only connection verified)
- [ ] Confirm products exist: MOU-001, GOV-001, CHAR-001, MARK-001
- [ ] Confirm identity resolutions applied: `php artisan migration:identity-resolve --apply`
- [ ] Run read-only audit: `php artisan migration:audit`
- [ ] Record `CUTOVER_TIMESTAMP` when legacy writes freeze

---

## Cutover freeze

At cutover moment:

1. **Stop** legacy loan/repayment/customer write operations
2. Record exact `CUTOVER_TIMESTAMP`
3. Take final legacy snapshot
4. Verify no writes occurred after snapshot

---

## Phase 1 — Reference data

**Pause and verify after each step.**

```bash
php artisan migration:audit
php artisan migration:reference-data --dry-run
```

Verify:

- 26 MOU employers (25 create + 1 match expected on fresh prod)
- Government/Marketeer/Character placeholders skipped
- 0 unexpected duplicate master data

```bash
php artisan migration:reference-data --promote
php artisan migration:reference-data --dry-run
php artisan migration:status
```

Gate: dry-run shows **0 would-create**.

---

## Phase 2 — Customers

```bash
php artisan migration:customers --dry-run
```

Verify:

- `true_customers_identified = 1936`
- `unique_intended_target_customers = 1934`
- `company_mapping_pending = 0`
- `marketeer_market_pending = 0`
- `manual_review = 0` (or approved exceptions documented)

```bash
php artisan migration:customers --promote
php artisan migration:customers --dry-run
php artisan migration:status
```

Gate:

- 1,934 distinct target customers
- Alias pairs 14+19 and 126+127 map to same targets
- 35/35 Marketeer markets mapped, `company_id = null`
- Government customers intentional `company_id = null`

---

## Phase 3 — Active loans

```bash
php artisan migration:active-loans --dry-run
```

Verify:

- 752 legacy active
- 750 promotable
- 2 manual review (loans 16969, 17617)
- 0 blocked missing customer

```bash
php artisan migration:active-loans --promote
php artisan migration:active-loans --dry-run
php artisan migration:status
```

Gate:

- 750 matched, 0 would-create
- Manual loans excluded

---

## Phase 4 — Repayments

```bash
php artisan migration:repayments --dry-run
```

Verify:

- A_DIRECT ≈ 6,541
- B_RECONSTRUCTED ≈ 2,600
- C_AMBIGUOUS = 2 (excluded)
- D_MANUAL ≈ 4,673 (excluded)

```bash
php artisan migration:repayments --promote
php artisan migration:repayments --dry-run
php artisan migration:status
```

Gate:

- `would_promote = 0`
- Payment conservation verified
- No duplicate `LEG-R-*` references

---

## Phase 5 — Reconciliation

```bash
php artisan migration:reconcile
php artisan migration:status
```

Gate:

- `FAIL = 0` for all auto-migrated active loans
- Per-loan variance `<= 0.01`

---

## Phase 6 — Application smoke test

Manually verify representative customers per product:

- MOU: view loan, balance, repayment history
- Government: same
- Character: same
- Marketeer: customer → market → group → MARK-001

Settlement and next-payment simulation on one loan per product before go-live.

---

## Rollback boundary

**Safe rollback window:** Before any new revamp financial transactions on migrated loans.

```bash
php artisan migration:rollback --run=<uuid>
```

After real post-cutover repayments/disbursements/accruals: **do not delete migrated loans**. Requires controlled accounting procedure.

---

## Do NOT migrate in this cutover

- Settled/historical inactive loans (later phase)
- Manual review cohort (user 1835, loans 16969/17617, repayments 28303/28308)

See `PRODUCTION-MANUAL-REVIEW.md`.

---

## Marketeer new origination

Keep MARK-001 new lending **disabled** until weekly schedule product configuration is implemented.
