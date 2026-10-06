# Parallel-run playbook (legacy live + revamp shadow)

Temporary workflow while legacy remains the production URL and revamp validates aging, accrual, and reconciliation.

**Full reference:** [../PARALLEL-RUN.md](../PARALLEL-RUN.md)

## Enable

```env
LEGACY_MIGRATION_DASHBOARD_ENABLED=true
LEGACY_PARALLEL_RUN_ENABLED=true
LEGACY_LOAN_POLLING_ENABLED=true
LEGACY_REPAYMENT_POLLING_ENABLED=true
LEGACY_LOAN_POLL_WATERMARK=12345   # highest legacy loan id at cutover start
LEGACY_POLL_INTERVAL_MINUTES=30
```

Ensure `php artisan schedule:run` runs every minute (cron) and queue workers are up.

## Flow

1. **Loan poller** (`migration:poll-legacy-loans`) — read-only legacy query for new `status_code=301` loans above watermark; stages rows in `migration_loan_inbox`.
2. **Dashboard** — `/legacy/migration-dashboard/loans/pending` lists inbox rows; admins with `migration.manage` confirm import.
3. **Import** — promotes loan via `ActiveLoanMigrator`, replays + promotes repayments for that customer, catch-up daily accrual, refresh schedule aging.
4. **Repayment poller** (`migration:poll-legacy-repayments`) — detects legacy repayments for mapped customers; stages `migration_repayment_inbox`; auto-sync promotes A/B attributions and updates loan ledgers.

## Manual commands

```bash
php artisan migration:poll-legacy-loans
php artisan migration:poll-legacy-repayments
php artisan migration:sync-legacy-repayments
php artisan loans:refresh-schedule-aging
php artisan loans:accrue-interest --from=2026-10-01 --to=2026-10-05
```

## Scheduled jobs (bootstrap/app.php)

| Time (Africa/Lusaka) | Command |
|----------------------|---------|
| 00:30 | `loans:sync-active-status` |
| 01:00 | `loans:refresh-schedule-aging` |
| 02:00 | `loans:accrue-interest` |
| 09:00 | `repayments:send-reminders` |
| Every N min | `migration:poll-legacy-loans` (if enabled) |
| Every N min | `migration:poll-legacy-repayments` + sync (if enabled) |

## Decommission after full cutover

1. Set `LEGACY_LOAN_POLLING_ENABLED=false` and `LEGACY_REPAYMENT_POLLING_ENABLED=false`
2. Drop inbox tables / remove poller commands (optional cleanup)
3. Disable migration dashboard per `MIGRATION-DASHBOARD.md`
