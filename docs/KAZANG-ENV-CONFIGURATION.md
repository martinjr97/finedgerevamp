# Kazang integration — environment variables

Reference for `.env` settings required to run the Kazang payment gateway in FineEdge Revamp.

Related: [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)

---

## Quick start (minimum)

These must be set before Kazang is operational:

```dotenv
KAZANG_ENABLED=true
KAZANG_USERNAME=
KAZANG_PASSWORD=
KAZANG_CHANNEL=Starlabs
KAZANG_CALLBACK_USERNAME=
KAZANG_CALLBACK_PASSWORD=
KAZANG_AMQP_HOST=
KAZANG_AMQP_PORT=5672
KAZANG_AMQP_USERNAME=
KAZANG_AMQP_PASSWORD=
```

Also ensure the Kazang gateway is **Active** in admin and linked to the **Kazang Treasury Wallet** (`KAZANG-TREASURY` from seeders).

---

## Master switch

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `KAZANG_ENABLED` | Yes | `false` | Enables Kazang provider, AMQP collections, XML-RPC disbursements, callback handling, and admin balance check. When `false`, the gateway appears inactive regardless of DB status. |

---

## Kazang API (XML-RPC)

Used for **disbursements** (`authClient` → `nfsCashIn` → `confirm`) and **admin balance check** (`authClient` → read `balance`).

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `KAZANG_API_HOST` | No | `api.kazang.net` | Kazang API hostname. Use `testapi.kazang.net` for UAT if applicable. |
| `KAZANG_API_PATH` | No | `/apimanager/api_v2/` | API path on the Kazang host. |
| `KAZANG_USERNAME` | Yes | *(empty)* | Merchant username from Kazang. |
| `KAZANG_PASSWORD` | Yes | *(empty)* | Merchant password from Kazang. |
| `KAZANG_CHANNEL` | No | `Starlabs` | Channel name registered with Kazang (legacy default). |
| `KAZANG_TIMEOUT` | No | `60` | HTTP timeout in seconds for Kazang API calls. |

### Disbursement product IDs

Operator-specific product IDs for mobile money cash-in (legacy values shown):

| Variable | Required | Default | Operator |
|----------|----------|---------|----------|
| `KAZANG_PRODUCT_AIRTEL` | No | `5308` | Airtel Money |
| `KAZANG_PRODUCT_MTN` | No | `5360` | MTN Money |
| `KAZANG_PRODUCT_ZAMTEL` | No | `5305` | Zamtel Kwacha |

---

## RabbitMQ (collections)

Repayment collections are **queued to RabbitMQ** for the existing Java consumer (same JSON shape as legacy FineEdge).

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `KAZANG_AMQP_HOST` | Yes* | `AMQP_HOST` or `localhost` | RabbitMQ host. |
| `KAZANG_AMQP_PORT` | No | `AMQP_PORT` or `5672` | RabbitMQ port. |
| `KAZANG_AMQP_USERNAME` | Yes* | `AMQP_USERNAME` or `guest` | RabbitMQ username. |
| `KAZANG_AMQP_PASSWORD` | Yes* | `AMQP_PASSWORD` or `guest` | RabbitMQ password. |
| `KAZANG_AMQP_QUEUE_AIRTEL` | No | `CollectionsAirtel` | Queue for Airtel collections. |
| `KAZANG_AMQP_QUEUE_MTN` | No | `CollectionsMTN` | Queue for MTN collections. |
| `KAZANG_AMQP_QUEUE_ZAMTEL` | No | `CollectionsZamtel` | Queue for Zamtel collections. |

\*Required for collections unless generic `AMQP_*` fallbacks are already set in `.env`.

**Note:** Queue names must match what the Java service consumes. Do not change unless Java is updated too.

---

## Callback (Java → Laravel)

Java posts repayment results to:

```http
POST /api/repayment/kazang/callback
```

Headers: `username`, `password`  
Body (JSON): `transaction_id` (= revamp repayment id), `transaction_status` (`301` = success)

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `KAZANG_CALLBACK_USERNAME` | Yes | *(empty)* | Expected `username` header on callback requests. |
| `KAZANG_CALLBACK_PASSWORD` | Yes | *(empty)* | Expected `password` header on callback requests. |
| `KAZANG_CALLBACK_SUCCESS_STATUS` | No | `301` | `transaction_status` value treated as confirmed (legacy default). |

The callback URL path is fixed for compatibility with the existing Java integration.

---

## Currency and balance display

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `KAZANG_DEFAULT_CURRENCY` | No | `ZMW` | Currency label for gateway attempts and admin balance popup. |
| `KAZANG_BALANCE_MODE` | No | `kwacha` | How to interpret `balance` from `authClient`. Use `kwacha` if the API returns major units; use `minor_units` if it returns ngwee (value ÷ 100). |

---

## Example `.env` block

```dotenv
# Kazang gateway
KAZANG_ENABLED=true
KAZANG_API_HOST=api.kazang.net
KAZANG_API_PATH=/apimanager/api_v2/
KAZANG_USERNAME=1002375104
KAZANG_PASSWORD=
KAZANG_CHANNEL=Starlabs
KAZANG_TIMEOUT=60

KAZANG_PRODUCT_AIRTEL=5308
KAZANG_PRODUCT_MTN=5360
KAZANG_PRODUCT_ZAMTEL=5305

KAZANG_AMQP_HOST=localhost
KAZANG_AMQP_PORT=5672
KAZANG_AMQP_USERNAME=guest
KAZANG_AMQP_PASSWORD=guest
KAZANG_AMQP_QUEUE_AIRTEL=CollectionsAirtel
KAZANG_AMQP_QUEUE_MTN=CollectionsMTN
KAZANG_AMQP_QUEUE_ZAMTEL=CollectionsZamtel

KAZANG_CALLBACK_USERNAME=
KAZANG_CALLBACK_PASSWORD=
KAZANG_CALLBACK_SUCCESS_STATUS=301

KAZANG_DEFAULT_CURRENCY=ZMW
KAZANG_BALANCE_MODE=kwacha
```

---

## Admin setup (not env — but required)

After env vars are set:

1. Run seeders (if not already):
   ```bash
   php artisan db:seed --class=TreasuryWalletSeeder
   php artisan db:seed --class=KazangPaymentGatewaySeeder
   php artisan db:seed --class=PaymentGatewayProductRuleSeeder
   ```
2. In **Admin → Payment Gateways**, set Kazang status to **Active**.
3. Confirm Kazang is linked to wallet `KAZANG-TREASURY`.
4. Configure **Gateway Routing** and/or **Product Gateway Rules** to use Kazang where needed (defaults remain cGrate).
5. Use **Check Kazang Balance** on the gateway detail page to verify API credentials.

---

## What each flow uses

| Flow | Transport | Env vars involved |
|------|-----------|-------------------|
| Mobile money collection | RabbitMQ → Java → callback | `KAZANG_ENABLED`, `KAZANG_AMQP_*`, `KAZANG_CALLBACK_*` |
| Mobile money disbursement | Direct XML-RPC | `KAZANG_ENABLED`, `KAZANG_API_*`, `KAZANG_USERNAME/PASSWORD/CHANNEL`, `KAZANG_PRODUCT_*` |
| Admin balance check | Direct XML-RPC (`authClient`) | `KAZANG_ENABLED`, `KAZANG_API_*`, `KAZANG_USERNAME/PASSWORD/CHANNEL`, `KAZANG_BALANCE_MODE` |

Bank disbursements remain on cGrate until Kazang bank support is added (`supports_bank=false` on the Kazang gateway record).
