# Parallel-run: legacy live + revamp shadow

This document describes the **temporary** two-system parallel period: legacy (`finedge`) stays the production URL while revamp (`finedge-revamp`) mirrors migrated portfolio data to validate loan aging, daily accrual, crons, and reconciliation before cutover.

**Remove or disable everything in this doc after full migration.**

Related:

- Migration dashboard UI: [data-migration/MIGRATION-DASHBOARD.md](data-migration/MIGRATION-DASHBOARD.md)
- Operator quick reference: [data-migration/PARALLEL-RUN-PLAYBOOK.md](data-migration/PARALLEL-RUN-PLAYBOOK.md)

---

## Architecture

```text
Legacy (live)                    Revamp (shadow)
─────────────                    ───────────────
Disbursements ──read-only poll──► migration_loan_inbox
Repayments    ──read-only poll──► migration_repayment_inbox
                                 Admin confirms import → target loans
                                 Crons: accrual, aging, reminders
                                 Reconciliation dashboard
```

Legacy is **never written to** by revamp. All polling uses the read-only `legacy` DB connection (configured from `/var/www/personal/finedge/.env` when `LEGACY_DB_*` is not set in revamp `.env`).

---

## Environment variables

Add to revamp `.env`:

```env
LEGACY_MIGRATION_DASHBOARD_ENABLED=true
LEGACY_PARALLEL_RUN_ENABLED=true
LEGACY_LOAN_POLLING_ENABLED=true
LEGACY_REPAYMENT_POLLING_ENABLED=true
LEGACY_LOAN_POLL_WATERMARK=19221
LEGACY_POLL_INTERVAL_MINUTES=30
LEGACY_POLL_BATCH_LIMIT=200
LEGACY_REPAYMENT_SYNC_BATCH_LIMIT=100
```

| Variable | Purpose |
|----------|---------|
| `LEGACY_MIGRATION_DASHBOARD_ENABLED` | Enables `/legacy/migration-dashboard` (404 when false) |
| `LEGACY_PARALLEL_RUN_ENABLED` | Master flag documented for ops; pollers use the specific flags below |
| `LEGACY_LOAN_POLLING_ENABLED` | Schedule + run `migration:poll-legacy-loans` |
| `LEGACY_REPAYMENT_POLLING_ENABLED` | Schedule + run `migration:poll-legacy-repayments` (+ auto sync) |
| `LEGACY_LOAN_POLL_WATERMARK` | Ignore legacy loans with `id <= watermark`; only new disbursements after bulk M2 promote |
| `LEGACY_POLL_INTERVAL_MINUTES` | Cron interval for both pollers (5–59 minutes) |
| `LEGACY_POLL_BATCH_LIMIT` | Max rows per poll cycle |
| `LEGACY_REPAYMENT_SYNC_BATCH_LIMIT` | Max repayment inbox rows promoted per sync |

Config file: `config/legacy-parallel-run.php`.

### Setting the watermark

Run **once** after bulk `migration:active-loans --promote` of the existing portfolio:

```bash
php artisan tinker --execute="
\App\Migration\LegacyConnection::configureFromLegacyEnvFile();
echo (int) \App\Migration\LegacyConnection::connection()
    ->table('loans')->where('status_code', '301')->max('id');
"
```

Set `LEGACY_LOAN_POLL_WATERMARK` to that value. Only legacy loans with `id > watermark` and `status_code = 301` appear in the pending import queue.

**Current local value (Oct 2026):** `19221` (756 active legacy loans at id ≤ 19221).

---

## Before starting the parallel period

1. Complete bulk M2 migration on revamp:
   - `migration:reference-data --promote`
   - `migration:customers --promote`
   - `migration:active-loans --promote`
   - `migration:repayments --promote`
   - `migration:reconcile`
2. Set `LEGACY_LOAN_POLL_WATERMARK` to max legacy active loan id (see above).
3. Enable env flags (see table).
4. Ensure scheduler cron on revamp server:

   ```cron
   * * * * * cd /var/www/personal/finedge-revamp && php artisan schedule:run >> /dev/null 2>&1
   ```

5. Ensure Redis queue worker / Horizon is running (gateway polling, SMS).

---

## Daily operator workflow

### 1. New legacy disbursements

1. Open [Pending Loans](http://127.0.0.1:8000/legacy/migration-dashboard/loans/pending) (or production revamp URL).
2. Review each row — amount, customer, detected time.
3. Click **Confirm import** (requires admin permission `migration.manage`).

Import automatically:

- Promotes the loan into revamp (`ActiveLoanMigrator`)
- Replays and promotes repayments for that customer
- Catch-up daily accrual from loan start through yesterday
- Refreshes schedule aging (`days_overdue`)

Or **Dismiss** if the row is a false positive.

Home dashboard shows an amber alert when pending loans or repayments exist.

### 2. Ongoing legacy repayments

No manual action needed when polling is enabled:

- `migration:poll-legacy-repayments` detects repayments for mapped customers
- Promotes A_DIRECT / B_RECONSTRUCTED attributions
- Updates loan ledgers and schedule aging

Check pending count on the dashboard; failed rows stay in `migration_repayment_inbox` with `sync_error`.

---

## Artisan commands

| Command | Description |
|---------|-------------|
| `migration:poll-legacy-loans` | Stage new legacy 301 loans in inbox |
| `migration:poll-legacy-repayments` | Stage + sync repayments (use `--no-sync` to poll only) |
| `migration:sync-legacy-repayments` | Promote pending repayment inbox rows |
| `loans:refresh-schedule-aging` | Refresh `days_overdue` on all active loans |
| `loans:accrue-interest` | Daily accrual (use `--from` / `--to` for catch-up) |
| `loans:sync-active-status` | Mark disbursed loans as active |

Examples:

```bash
php artisan migration:poll-legacy-loans
php artisan migration:poll-legacy-repayments
php artisan loans:accrue-interest --from=2026-10-01 --to=2026-10-05
php artisan loans:refresh-schedule-aging --loan-id=123
```

---

## Scheduled jobs (Africa/Lusaka)

Registered in `bootstrap/app.php`:

| Time | Command |
|------|---------|
| 00:30 | `loans:sync-active-status` |
| 01:00 | `loans:refresh-schedule-aging` |
| 02:00 | `loans:accrue-interest` |
| 09:00 | `repayments:send-reminders` |
| Every `LEGACY_POLL_INTERVAL_MINUTES` | `migration:poll-legacy-loans` (if enabled) |
| Every `LEGACY_POLL_INTERVAL_MINUTES` | `migration:poll-legacy-repayments` (if enabled) |

Gateway status polling runs every minute via queue.

---

## Database tables (temporary)

| Table | Purpose |
|-------|---------|
| `migration_loan_inbox` | New legacy loans awaiting admin import |
| `migration_repayment_inbox` | Legacy repayments awaiting sync |
| `migration_sync_state` | Poll/sync timestamps |

Existing M2 tables (`migration_entity_maps`, `migration_loans`, etc.) are unchanged.

---

## Permissions

| Permission | Access |
|------------|--------|
| `migration.view` | Read migration dashboard |
| `migration.manage` | Confirm loan import, dismiss inbox rows, identity resolution |

`super-admin` receives both via `PermissionSeeder`.

---

## Validation checklist (weekly during parallel run)

- [ ] Pending loan queue reviewed within 24h of detection
- [ ] Reconciliation page: legacy vs target outstanding variance acceptable
- [ ] Sample daily-accrual loans: `loan_accruals` row per day, `last_accrual_date` advancing
- [ ] Sample overdue loans: `days_overdue` matches legacy PAR expectations
- [ ] Repayment inbox: no growing backlog of `failed` rows
- [ ] Scheduler heartbeat / cron logs show jobs running

---

## Troubleshooting

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| No new loans in inbox | Watermark too high or polling disabled | Check `LEGACY_LOAN_POLLING_ENABLED`, re-run poll manually |
| Import blocked: customer not mapped | Customer missing from M2 | Map customer on dashboard, then retry import |
| Repayment sync skipped (C/D class) | Ambiguous/manual attribution | Review on repayments/exceptions pages |
| Accrual not running | Scheduler not running | Verify cron + `loans:accrue-interest` manually |
| Legacy connection fails | Legacy `.env` unreadable | Set `LEGACY_DB_*` explicitly in revamp `.env` |

---

## Decommission after full cutover

1. Point production URL to revamp; decommission legacy app.
2. Set in `.env`:

   ```env
   LEGACY_LOAN_POLLING_ENABLED=false
   LEGACY_REPAYMENT_POLLING_ENABLED=false
   LEGACY_PARALLEL_RUN_ENABLED=false
   LEGACY_MIGRATION_DASHBOARD_ENABLED=false
   ```

3. Optional code cleanup: remove poller commands, inbox repositories, dashboard pending pages, `config/legacy-parallel-run.php`.
4. Optional schema cleanup: drop `migration_loan_inbox`, `migration_repayment_inbox`, `migration_sync_state`.
5. Archive this document to `docs/data-migration/archive/PARALLEL-RUN.md`.

---

## Code map

| Area | Location |
|------|----------|
| Config | `config/legacy-parallel-run.php` |
| Loan poller | `app/Migration/ParallelRun/LegacyLoanPollService.php` |
| Repayment poller/sync | `app/Migration/ParallelRun/LegacyRepaymentPollService.php`, `LegacyRepaymentSyncService.php` |
| Confirm import | `app/Migration/ParallelRun/ParallelRunLoanImportService.php` |
| Dashboard | `app/Http/Controllers/Admin/LegacyMigrationDashboardController.php` |
| Pending UI | `resources/views/legacy/migration-dashboard/loans/pending*.blade.php` |
| Accrual on migrate | `app/Migration/Phases/Support/MigratedLoanAccrualAttributes.php` |
| Schedule | `bootstrap/app.php` |
