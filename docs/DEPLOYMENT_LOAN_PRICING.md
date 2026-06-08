# Loan Pricing Refactor — Deployment Checklist

Deploy after Phases 1–8 (schema, pricing engine, schedules, settlement, rate UI, labels, regression tests).

## Pre-deploy

- [ ] Review open PR / diff on `main` or release branch
- [ ] Confirm **no manual SQL** on production loans (historical loans are not backfilled)
- [ ] Backup database (full dump or snapshot)
- [ ] Note current app version / commit SHA for rollback
- [ ] Put app in maintenance mode if your process requires it

## Migrations (run in order)

```bash
php artisan migrate --force
```

Expected new migrations (2026-05-18 series):

| Migration | Purpose |
|-----------|---------|
| `2026_05_18_100000_add_loan_pricing_architecture_columns` | `interest_behavior`, `rate_input_mode`, term %, bands, loan quote fields |
| `2026_05_18_140000_add_schedule_component_fields_to_loan_payment_schedules_table` | Schedule principal/fee/interest components |
| `2026_05_18_150000_add_metadata_to_loan_repayments_table` | Settlement metadata on repayments |
| `2026_05_18_160000_update_loan_rates_unique_for_amount_bands` | Drops unique `(rate_type, tenure)` — bands enforced in app |

## Post-migrate verification

```bash
php artisan migrate:status
php artisan test --filter=Loan
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Application deploy

- [ ] Deploy code (same commit as tested)
- [ ] Restart PHP-FPM / queue workers / scheduler
- [ ] Confirm `loans:accrue-interest` cron still scheduled
- [ ] Smoke-test admin: rate type create (term % + upfront), import template, loan show labels
- [ ] Smoke-test settlement quote on a **test** daily-accrual loan (non-production if possible)

## Configuration

- No new `.env` keys required for core pricing
- Existing products/rate types without `rate_input_mode` infer legacy daily/weekly multiplier from `accrual_period`

## Rollback notes

**If deploy must be reverted:**

1. Restore previous code release
2. **Do not** run `migrate:rollback` on production if new loans were created with new columns — data may reference new fields
3. Safe rollback path when **no new-format loans** were booked:
   ```bash
   php artisan migrate:rollback --step=4
   ```
4. If new loans exist, prefer **forward fix** rather than schema rollback
5. Restore DB from backup if partial migration or corrupt state

**Risk:** Dropping `loan_rates` unique index allows duplicate tenure rows; rolling back migration re-enables unique `(loan_rate_type_id, tenure_months)` and may **fail** if duplicate bands were imported.

## UAT script (business users)

### A. Rate setup (admin)

1. Create rate type: **Term percentage**, **Upfront flat**, 1-month product
2. Add rate row: tenure 1, **term interest 27.8%**, **processing fee 5%**
3. Download import template — confirm **Rates** + **Instructions** sheets
4. Import a second row (e.g. tenure 3, 45% interest, optional min/max principal band)
5. Copy rate type to another product — confirm modes and rows copy

### B. Upfront flat loan

1. Originate **ZMW 10,000**, 1 month, upfront flat
2. Confirm: interest **2,780**, fee **500**, **booked outstanding 13,280**
3. Repayment schedule total ≈ **13,280** (not split as “projected” daily accrual)
4. Early settlement mid-term — confirm **rebate** on unearned interest, payoff &lt; full schedule total

### C. Daily accrual loan

1. Originate **ZMW 10,000**, 1 month, daily accrual, same rate row
2. Confirm: **booked outstanding 10,500** (principal + fee only)
3. Confirm: **projected repayment ~13,280** on loan show and schedule
4. Run one accrual day — booked outstanding increases; earned interest &gt; 0
5. Settlement quote — payoff **excludes** unearned projected interest
6. Customer dashboard shows **booked outstanding** separately from **projected full repayment**

### D. Legacy loans

1. Open an **old** loan (no `interest_behavior`) — page loads without error
2. Labels use booked totals; behavior unchanged vs before deploy

### E. Repayments

1. Customer submits pending repayment (non-integrated channel)
2. Admin approves with **channel + bank account** — repayment applies to loan

---

**Sign-off:** Product owner ______________  IT ______________  Date ______________
