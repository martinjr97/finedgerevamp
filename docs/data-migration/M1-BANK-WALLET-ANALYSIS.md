# M1 — Bank & Wallet Analysis

**Date:** 2026-08-22  
**Legacy:** `/var/www/personal/finedge`  
**Revamp:** `/var/www/personal/finedge-revamp`

---

## Executive summary

Legacy FinEdge stores **customer payment destinations differently** from the revamp:

| Destination | Legacy | Revamp |
|-------------|--------|--------|
| Customer bank account | Denormalized columns on `customers` | `customer_payment_details` (method_type=bank) + `financial_institutions` |
| Customer mobile money | `users.phone_number` (disbursement MSISDN) | `customer_payment_details` (method_type=wallet) + `wallet_providers` |
| Company treasury banks | `banks` (2 rows) | `banks` (operator accounts) |
| Company treasury wallets | `payment_wallets` (5 rows) | `wallets` (operator float) |

**Critical finding for active portfolio:** **0 of 716 active customers** have populated `customers.bank_account_number` / `account_bank_name`. All active customers have phone numbers usable as mobile wallet destinations (confidence MEDIUM — inferred from disbursement behaviour).

---

## Legacy — customer bank accounts

### Storage

No `bank_accounts` table. Customer bank data lives on **`customers`**:

| Column | Purpose |
|--------|---------|
| `account_bank_name` | Free-text bank name |
| `account_branch_name` | Branch |
| `account_branch_sort_code` | Sort/branch code |
| `bank_account_number` | Account number |

Added in migration `2021_07_14_001824_add_new_colums_to_clients_customer_table.php`.

### Usage in legacy code

- KYC forms, imports, customer profile display
- **Not used** in automated mobile-money disbursement
- Disbursement uses `users.phone_number` + `transactions.MSISDN` routed by `services.id`

### Active portfolio profile

| Metric | Count |
|--------|------:|
| Active customers with bank columns populated | **0** |
| Active customers without bank | **716** |
| Multiple bank accounts per customer | **0** (legacy schema allows only inline fields) |

**Target limitation:** Revamp `customer_payment_details` supports **one** method per customer (bank OR wallet). Flag: `BANK_ACCOUNT_MULTIPLE_TARGET_LIMITATION` if legacy ever had multiple — not observed in active portfolio.

---

## Legacy — treasury banks (`banks`)

| id | name | code | active |
|----|------|------|--------|
| 1 | Zambia Industrial Commercial Bank | ZICB | yes |
| 2 | First National Bank | FNB | yes |

Used for: company receipt/disbursement ledger (`repayments.bank_id`, `expenses.bank_id`, `loans.fund_source_type=bank`).

**Not customer bank master data.**

### Legacy → target mapping (treasury)

| Legacy | Target | Confidence |
|--------|--------|:----------:|
| FNB | `financial_institutions.code=FNB` | HIGH |
| ZICB | No direct match in revamp seeder | MANUAL_REVIEW |

---

## Legacy — customer mobile money / wallets

### Storage

No per-customer wallet table. Mobile money destination = **`users.phone_number`**.

Legacy code treats phone as disbursement MSISDN when `services.id` is Airtel/MTN/Zamtel mobile money (5–7). Network inferred from prefix (26097/77=Airtel, 26096/76=MTN, 26095/75=Zamtel).

### Treasury wallets (`payment_wallets`)

| id | name | code | type |
|----|------|------|------|
| 1 | Kazang | KAZANG | MOBILE_MONEY |
| 2 | Airtel Money | AIRTEL | MOBILE_MONEY |
| 3 | MTN Money | MTN | MOBILE_MONEY |
| 4 | Zamtel Money | ZAMTEL | MOBILE_MONEY |
| 5 | Other Wallet | OTHER | OTHER |

These are **operator treasury floats**, not customer wallets.

### Active portfolio profile

| Metric | Count |
|--------|------:|
| Active customers with phone (wallet proxy) | **716** |
| Active customers without phone | **0** |
| Duplicate normalized wallet numbers | **0** |
| Invalid/malformed numbers | **0** (after normalization) |
| Shared wallet between customers | **0** detected |

### Provider inference (from MSISDN prefix)

Normalization target: `260XXXXXXXXX` (matches revamp conventions).

| Prefix | Provider code |
|--------|---------------|
| 26097, 26077 | AIRTEL_MONEY |
| 26096, 26076 | MTN_MONEY |
| 26095, 26075 | ZAMTEL_MONEY |

Confidence: **MEDIUM** (phone used operationally as wallet; not a dedicated wallet entity in legacy).

### Legacy treasury → target wallet provider mapping

| Legacy `payment_wallets` | Target `wallet_providers` | Notes |
|--------------------------|---------------------------|-------|
| AIRTEL | AIRTEL_MONEY | HIGH |
| MTN | MTN_MONEY | HIGH |
| ZAMTEL | ZAMTEL_MONEY | HIGH |
| KAZANG | — | Treasury only; MANUAL_REVIEW |
| OTHER | — | MANUAL_REVIEW |

---

## Revamp — payment destination model

```
Customer
  └── customer_payment_details (ONE row: bank OR wallet)
        ├── bank → financial_institutions + branches
        └── wallet → wallet_providers + wallet_number

Loan (per disbursement)
  └── disbursement_* fields + channel_id
  └── disbursed_via_type/id → operator Bank/Wallet (treasury)
```

Operator treasury (`banks`, `wallets` tables) ≠ customer destinations.

---

## Payment destination usage

| Flow | Legacy | Revamp |
|------|--------|--------|
| Loan disbursement to customer | `transactions.MSISDN`, `services.id` | `disbursement_phone_number`, `channel_id`, `customer_payment_details` |
| Customer repayment | Phone match + Kazang queues; treasury via `repayments.wallet_id` | `Repayment` + `loan_repayments`; treasury via `received_via_*` |
| Refunds | Not fully modeled | `LoanRepayment` refund transaction type |

---

## Migration confidence classification

| Record type | Typical confidence | Notes |
|-------------|:------------------:|-------|
| Customer phone → wallet | MEDIUM | Operational truth; not explicit wallet entity |
| Customer bank columns | HIGH when populated | Currently NOT_PRESENT for active book |
| Treasury bank FNB | HIGH | Direct code match |
| Treasury bank ZICB | MANUAL_REVIEW | Add FI or map manually |
| Treasury Kazang | MANUAL_REVIEW | No customer provider equivalent |

---

## M1 migration actions

1. Import `users.phone_number` → `customer_payment_details.method_type=wallet` where no bank present
2. Infer `wallet_provider_id` from normalized prefix
3. Do **not** fail customers with no bank (genuinely absent in legacy)
4. Stage treasury bank/wallet mappings separately from customer destinations
5. Preserve `legacy_user_id` in customer `metadata`

---

## Exceptions

See [M1-ACTIVE-MIGRATION-EXCEPTIONS.md](./M1-ACTIVE-MIGRATION-EXCEPTIONS.md).

---

## Related files

- `app/Migration/BankWalletAnalyzer.php`
- `app/Migration/PhoneNormalizer.php`
- `docs/data-migration/tools/m1-analyze-full.json`
