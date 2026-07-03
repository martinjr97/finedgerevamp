# Horizon Queue Operations

Operational guide for FineEdge queue processing with Laravel Horizon, Redis, and priority-based financial queues.

## Architecture overview

FineEdge uses **seven queues** grouped by business priority:

| Queue | Purpose | Redis connection |
|-------|---------|------------------|
| `payments-high` | Collection initiation, callbacks | `financial` |
| `payments` | Collection polling, reconciliation | `financial` |
| `disbursements-high` | Disbursement initiation, callbacks | `financial` |
| `disbursements` | Disbursement polling, payout reconciliation | `financial` |
| `notifications` | Emails, SMS, admin invites, password resets | `default` |
| `reports` | Reports, exports, analytics (when queued) | `default` |
| `maintenance` | Cleanup, archive, housekeeping | `default` |

### Polling separation

Initiation and callbacks use **high** queues. Polling and reconciliation use **standard** financial queues so polling cannot starve new customer requests.

**Priority rules (Financial Supervisor queue order):**

1. `payments-high` before `payments` — new collections before collection polling
2. `disbursements-high` before `disbursements` — new disbursements before payout polling
3. All collection queues before disbursement queues — collect money before sending money

## Supervisor architecture

Horizon runs **three supervisors**:

### 1. Financial Supervisor (`supervisor-financial`)

- **Connection:** `redis-financial`
- **Queues (order):** `payments-high`, `payments`, `disbursements-high`, `disbursements`
- **Production:** `minProcesses=2`, `maxProcesses=10`, `balance=auto`

### 2. Application Supervisor (`supervisor-application`)

- **Connection:** `redis`
- **Queues:** `notifications`, `reports`, `default`
- **Production:** `minProcesses=1`, `maxProcesses=5`

### 3. Maintenance Supervisor (`supervisor-maintenance`)

- **Connection:** `redis`
- **Queue:** `maintenance`
- **Production:** `minProcesses=1`, `maxProcesses=2`, `nice=10`

## Redis architecture

Two logical Redis connections allow future infrastructure split without code changes:

| Connection | Env prefix | Default DB | Used for |
|------------|------------|------------|----------|
| `default` | `REDIS_*` | 0 | Application queues, cache, Horizon meta |
| `financial` | `REDIS_FINANCIAL_*` | 2 | Payment and disbursement queues |

Queue connections in `config/queue.php`:

- `redis` → default Redis, application queues
- `redis-financial` → financial Redis, gateway jobs

## Environment variables

Copy from `.env.example`:

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

REDIS_FINANCIAL_HOST=127.0.0.1
REDIS_FINANCIAL_PORT=6379
REDIS_FINANCIAL_DB=2
FINANCIAL_QUEUE_CONNECTION=redis-financial

HORIZON_PATH=horizon

PAYMENTS_QUEUE_HIGH=payments-high
PAYMENTS_QUEUE=payments
DISBURSEMENTS_QUEUE_HIGH=disbursements-high
DISBURSEMENTS_QUEUE=disbursements
NOTIFICATIONS_QUEUE=notifications
REPORTS_QUEUE=reports
MAINTENANCE_QUEUE=maintenance
```

## Retry strategy

| Job type | Horizon `tries` | Notes |
|----------|-----------------|-------|
| Collection/disbursement initiation | 1 | Business logic schedules polling; no automatic SOAP retries |
| Status/polling jobs | 5 | Transient gateway/network errors |
| Notifications | 3 | Standard deliverability |
| Reports | 2 | Low urgency |
| Maintenance | 2 | Low urgency |

Config source: `config/queues.php` → `retries` array.

## Correlation ID strategy

The canonical correlation identifier is **`payment_gateway_attempts.internal_reference`** (e.g. `FINEDGE-123-456-ABC`).

It appears in:

- cGrate SOAP requests as `paymentReference` / `depositorReference`
- `payment_gateway_logs.payload.correlation_id`
- Application logs via `correlation_id` context key
- Horizon job tags: `correlation:FINEDGE-...`

**Operations search:**

1. Horizon → Monitoring → Tags → search `correlation:FINEDGE-...`
2. Admin → Payment Operations → Failed Financial Jobs
3. Database: `payment_gateway_attempts.internal_reference`

## Horizon tags

Financial jobs automatically expose tags for Horizon Monitoring:

**Collections**

- `payment`
- `loan:{loanId}`
- `customer:{customerId}`
- `gateway:{gatewayCode}`
- `direction:collection`
- `correlation:{internal_reference}`

**Disbursements**

- `disbursement`
- `loan:{loanId}`
- `customer:{customerId}`
- `gateway:{gatewayCode}`
- `wallet:{linkedWalletId}` (when applicable)
- `direction:disbursement`
- `correlation:{internal_reference}`

**Notifications** (when queued): `notification`, `email` / `sms`

**Reports** (when queued): `report`

Built by `GatewayHorizonTagBuilder` — no duplicate identifiers beyond `internal_reference`.

## Operational dashboard

**Admin → Configurations → Payment Operations** (`/admin/payment-operations`)

Displays:

- Collections/disbursements waiting, processing, failed, completed today
- Average processing times
- Oldest open attempt per direction
- Redis and Horizon status
- Failed financial job count
- Recent failed jobs with links to recovery UI

Super-admins also see a summary widget on the main dashboard.

## Failed financial jobs

**Admin → Configurations → Failed Financial Jobs** (`/admin/payment-operations/failed-jobs`)

Operations can view, retry, or discard failed payment/disbursement jobs without opening Horizon.

| Field | Shown |
|-------|-------|
| Correlation ID | Yes |
| Gateway, direction, loan, customer | Yes |
| Queue, job class, failed at | Yes |
| Exception summary | Yes (truncated) |
| Full job payload / secrets | **No** |

- **View** — `payment-gateways.view` or `payment-gateways.manage`
- **Retry / Discard** — `payment-gateways.manage` only
- Discard requires confirmation

## Health check command

Standard pre-go-live operational check:

```bash
php artisan payments:health
```

Reports **PASS**, **WARNING**, or **FAIL** for:

- Redis (default + financial)
- Queue connection
- Horizon running
- Scheduler heartbeat
- Queue names configured
- Failed financial jobs count
- Pending collection/disbursement attempts
- cGrate enabled
- Gateway routing and wallet/bank route readiness

Example output:

```text
FineEdge Payment Platform Health Check

Redis (default):                          PASS — Connected
Financial Redis:                          PASS — Connected
Queue connection:                         WARNING — sync (not production-ready)
Horizon running:                          WARNING — Skipped (sync queue)
Scheduler running:                        WARNING — No recent heartbeat
...
Overall: WARNING
```

## Operational monitoring

### Horizon dashboard

- URL: `/horizon` (configurable via `HORIZON_PATH`)
- **Production:** super-admin only
- **Local:** any authenticated admin

### Useful commands

```bash
php artisan payments:health
php artisan horizon
php artisan horizon:status
php artisan horizon:pause
php artisan horizon:continue
php artisan horizon:terminate
php artisan queue:failed
php artisan queue:retry all
php artisan queue:retry {uuid}
php artisan queue:forget {uuid}
```

## Scheduler (required separately)

Horizon manages **workers only**. The Laravel scheduler still requires cron:

```cron
* * * * * cd /var/www/personal/finedge-revamp && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include:

- `gateway-poll-due-attempts` (every minute) — dispatches polling jobs to `payments` / `disbursements`
- `operations-scheduler-heartbeat` (every minute) — health check heartbeat
- `loans:accrue-interest` (daily 01:00 Africa/Lusaka)
- `repayments:send-reminders` (daily 09:00 Africa/Lusaka)

## PaymentQueue compatibility

`App\PaymentPlatform\Support\PaymentQueue` remains as a **deprecated** wrapper delegating to `FinancialQueue`:

- `PaymentQueue::high()` → `payments-high`
- `PaymentQueue::polling()` → `payments`

Use `FinancialQueue` for all new code.

## Deployment checklist

1. Install Redis server and PHP `ext-redis` (phpredis)
2. Set queue/redis/financial env vars in production `.env`
3. Run migrations: `php artisan migrate --force`
4. Cache config: `php artisan config:cache`
5. Start Horizon via systemd/supervisor
6. Confirm scheduler cron is active
7. Run `php artisan payments:health` — resolve FAIL items
8. Verify `/horizon` requires super-admin authentication
9. Run `php artisan horizon:status` — all three supervisors running
10. Test a collection; confirm initiation on `payments-high`, polling on `payments`

## Recovery procedures

### Pause processing

```bash
php artisan horizon:pause
```

### Resume processing

```bash
php artisan horizon:continue
```

### Inspect failed jobs

Use **Failed Financial Jobs** admin page, or:

```bash
php artisan queue:failed
php artisan queue:retry {uuid}
```

### Rollback to database driver (emergency)

1. Set `QUEUE_CONNECTION=database` in `.env`
2. Stop Horizon systemd unit
3. Run legacy worker with all financial queues
4. `php artisan config:cache`

## Queue assignment reference

Centralized via `FinancialQueue` and `ApplicationQueue`.

| Component | Queue | Connection |
|-----------|-------|------------|
| `DispatchGatewayCollectionJob` | `payments-high` | `redis-financial` |
| `QueryGatewayAttemptStatusJob` (collection callback) | `payments-high` | `redis-financial` |
| `QueryGatewayAttemptStatusJob` (collection polling) | `payments` | `redis-financial` |
| `DispatchGatewayDisbursementJob` | `disbursements-high` | `redis-financial` |
| `QueryGatewayAttemptStatusJob` (disbursement callback) | `disbursements-high` | `redis-financial` |
| `QueryGatewayAttemptStatusJob` (disbursement polling) | `disbursements` | `redis-financial` |
| Admin notifications | `notifications` | `redis` |
| Future exports | `reports` | `redis` |
| Future maintenance | `maintenance` | `redis` |

**Dispatch patterns:**

```php
// Polling (default)
QueryGatewayAttemptStatusJob::dispatchForAttempt($attemptId, $delay);

// Callback / urgent verification
QueryGatewayAttemptStatusJob::dispatchForAttempt($attemptId, null, FinancialJobPriority::High);
```

## Monitoring recommendations

- Run `payments:health` after deploy and in monitoring cron (alert on FAIL)
- Alert when failed financial jobs > 0 for more than 15 minutes
- Monitor Horizon long-wait thresholds for `payments-high` and `disbursements-high`
- Review Payment Operations dashboard during UAT soak tests
- Use Horizon tag search `correlation:FINEDGE-...` for incident triage
