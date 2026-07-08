# FineEdge SMS Zamtel Gateway

## Architecture

FineEdge uses a provider-based SMS platform under `app/Sms/`, separate from Laravel mail notifications in `app/Notifications/`.

```
SmsService::queueSend()
    → sms_messages (audit)
    → SendSmsJob (notifications queue)
        → SmsGatewayManager
            → LogSmsGateway | ZamtelSmsGateway
```

### Components

| Component | Purpose |
|-----------|---------|
| `SmsGatewayInterface` | Contract for providers |
| `LogSmsGateway` | Safe default — logs preview only, no HTTP |
| `ZamtelSmsGateway` | Zamtel Bulk SMS HTTP API |
| `SmsService` | Queue/send orchestration |
| `SmsHealthService` | Operational snapshot for CLI and admin UI |
| `SendSmsJob` | Async delivery with retries |
| `sms_messages` | Persistent audit (OTP bodies redacted) |

## Environment configuration

Safe defaults (`.env.example`):

```env
SMS_PROVIDER=log
SMS_ENABLED=false
NOTIFICATIONS_SMS_QUEUE=notifications
NOTIFICATIONS_LISTENER_QUEUE=default
SMS_FROM=
SMS_MAX_LENGTH=159

ZAMTEL_SMS_BASE_URL=https://bulksms.zamtel.co.zm
ZAMTEL_SMS_API_KEY=
ZAMTEL_SMS_SENDER_ID=FineEdge
ZAMTEL_SMS_TIMEOUT=30
ZAMTEL_SMS_CONNECT_TIMEOUT=10
ZAMTEL_SMS_VERIFY_SSL=true
```

**Never commit real API keys.**

### Production enablement

1. Set `SMS_PROVIDER=zamtel`
2. Set `SMS_ENABLED=true`
3. Configure `ZAMTEL_SMS_API_KEY` and approved `ZAMTEL_SMS_SENDER_ID`
4. Ensure Horizon processes the `notifications` queue

## Zamtel API format

- **Method:** `GET`
- **URL:** `{base_url}/api/v2.1/action/send/api_key/{apiKey}/contacts/{contacts}/senderId/{senderId}/message/{message}`
- **Contacts:** Bracketed international MSISDN, e.g. `[260977000001]`
- **Auth:** API key in URL path (redacted in all logs/responses)
- **Success:** HTTP `200` or `202` with JSON `success: true`

## Queueing

`SendSmsJob` uses:

- Connection: `ApplicationQueue::connection()` (Redis)
- Queue: `config('sms.queues.sms')` → default `notifications`
- Retries: 3 attempts, backoff 30s / 120s / 300s

Horizon tags: `sms`, `sms:{id}`, `provider:{provider}`, `category:{category}`, `recipient:{masked}`

## Commands

### Health check

```bash
php artisan sms:health
```

Reports configuration, Redis, provider readiness, pending/failed counts. **Does not send SMS.**

### Test send

```bash
# Log provider (default) — safe locally
php artisan sms:test --to=260977000001 --message="Test message"

# Zamtel UAT (requires --force when SMS_ENABLED=false)
php artisan sms:test --to=260977000001 --message="Test" --provider=zamtel --force
```

## OTP security

- OTP and security category messages store `[REDACTED OTP MESSAGE]` in `sms_messages.message_body`
- Actual message body is passed only in the queued job payload (short-lived)
- Application logs must never contain plaintext OTP codes
- Admin SMS Operations page shows previews only (redacted for sensitive categories)

## Admin UI

**Configuration → SMS Operations** (`/admin/sms-operations`)

Requires `sms-operations.view` or `sms-operations.manage`.

## UAT steps

1. `php artisan migrate`
2. `php artisan sms:health` — expect PASS/WARNING with defaults
3. `SMS_PROVIDER=log SMS_ENABLED=true php artisan sms:test --to=26097xxxxxxx --message="Hello"`
4. Verify `sms_messages` row and Horizon job on `notifications` queue
5. Configure Zamtel credentials in `.env` (not committed)
6. `php artisan sms:test --provider=zamtel --force --to=... --message="UAT"`
7. Test customer password reset OTP flow (web)

## Production checklist

- [ ] `QUEUE_CONNECTION=redis` and Horizon running
- [ ] `notifications` queue supervised in Horizon
- [ ] `SMS_ENABLED=true`, `SMS_PROVIDER=zamtel`
- [ ] Zamtel API key and sender ID configured via env/secrets manager
- [ ] `ZAMTEL_SMS_VERIFY_SSL=true` in production
- [ ] OTP flows verified — no plaintext in logs
- [ ] `php artisan sms:health` returns PASS

## Troubleshooting

| Issue | Check |
|-------|-------|
| SMS skipped with `disabled` | `SMS_ENABLED=false` — expected in dev |
| SMS skipped with `invalid_phone` | Phone must be valid Zambian MSISDN |
| Connection errors (retryable) | Network, SSL, Zamtel host reachability |
| `missing_credentials` | `ZAMTEL_SMS_API_KEY` or sender ID empty |
| Jobs not processing | Horizon supervisors, `notifications` queue |
| API key in logs | Should never happen — report immediately |

## Deferred (out of scope)

- SMPP provider
- SMS balance ledger and low-balance email alerts
- Bulk marketing SMS
- Loan/repayment/disbursement SMS (via `CustomerNotificationService`)
- WhatsApp / push notifications
