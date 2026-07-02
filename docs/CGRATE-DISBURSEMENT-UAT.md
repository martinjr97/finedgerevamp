# cGrate Disbursement UAT Guide

Document date: 2026-07-02  
Status: **UAT checklist — required before production disbursement**  
Related: [CGRATE-DISBURSEMENT-ROADMAP.md](./CGRATE-DISBURSEMENT-ROADMAP.md), D1 implementation in `app/PaymentPlatform/`

---

## Purpose

FineEdge D1 implements loan disbursement via cGrate `processCashDeposit`, but **provider behaviour is not production-verified**. This document defines assumptions, test scenarios, capture templates, and go/no-go criteria.

**UAT tools (D1.5):**

| Tool | Command |
|------|---------|
| Issuer discovery | `php artisan cgrate:cash-deposit-issuers` |
| Provider-only payout test | `php artisan cgrate:test-cash-deposit` |
| Safe UAT logs | `storage/logs/cgrate-uat-*.log` |

These tools **do not** mark loans disbursed or debit FineEdge wallets (except real cGrate merchant float when using the test command without `--dry-run`).

---

## Current assumptions (D1 implementation)

| Assumption | Risk if wrong |
|------------|---------------|
| `processCashDeposit` `responseCode=0` means **accepted**, not completed | Premature loan completion (mitigated: D1 only completes on query confirm) |
| `queryCustomerPayment(depositorReference)` works for disbursements | Polling never confirms; loans stuck in `processing` |
| Query codes `207` / `226` (or `0` + `paymentId`) mean payout complete | Wallet debited too early or never |
| Mobile `issuerName` = `MTN`, `Airtel`, `Zamtel` | Silent payout failure |
| Bank `issuerName` = exact cGrate registry name | Bank payouts fail |
| No reliable callback for cash deposits | Must rely on polling |
| Insufficient merchant float returns a terminal error code | Ops cannot detect float issues |

---

## Confirmed from collections only (ZAQA / FineEdge P1)

These apply to **`processCustomerPayment` / `queryCustomerPayment`** — analogous but **not proven** for cash deposits.

| Stage | Code / behaviour | Meaning |
|-------|------------------|---------|
| Initiate | `0` | Request accepted; **not** final payment |
| Initiate | `206`, `8`, `17`, `106` | Pending |
| Query | `207`, `226` | Processed / approved |
| Query | `0` + non-empty `paymentId` | Approved (query only) |
| Query | `0` without `paymentId` | Still pending |
| Terminal fail | `208` rejected; `7`, `210`, `214` failed | Do not complete loan |

---

## Unconfirmed for `processCashDeposit`

- [ ] Initiate `0` = accepted only vs completed  
- [ ] Whether `queryCustomerPayment` accepts `depositorReference`  
- [ ] Final success codes on query for cash deposits  
- [ ] Codes for insufficient cGrate float, invalid account, duplicate reference  
- [ ] Whether cGrate sends HTTP callbacks for cash deposits  
- [ ] Exact bank `issuerName` strings  

---

## Expected SOAP request fields

**Endpoint:** `{CGRATE_BASE_URL}{CGRATE_ENDPOINT_PATH}` (default `/Konik/KonikWs`)  
**Auth:** WS-Security UsernameToken (same as collections)

### `processCashDeposit`

| Field | Mobile money example | Bank example |
|-------|---------------------|--------------|
| `transactionAmount` | `5.00` | `5.00` |
| `customerAccount` | `0978967132` (local format) | Bank account number |
| `issuerName` | `Airtel` / `MTN` / `Zamtel` | Exact name from issuer discovery |
| `depositorReference` | Unique, e.g. `FINEDGE-OUT-{loanId}-…` | Same |

### `getAvailableCashDepositIssuers`

No body fields. Used to map FineEdge `financial_institutions.name` → cGrate `issuerName`.

### `queryCustomerPayment` (UAT)

| Field | Value |
|-------|-------|
| `paymentReference` | Same as `depositorReference` used at initiate |

---

## UAT test matrix

Run on **cGrate test/sandbox** credentials first. Record every response in the capture table below.

| # | Scenario | How to run | Pass criteria |
|---|----------|------------|---------------|
| 1 | Mobile money K5 | Admin loan show **or** `cgrate:test-cash-deposit --amount=5 --account=097… --issuer=Airtel --query` | Initiate accepted; query eventually confirms; loan completes only after confirm; wallet debited once |
| 2 | Bank K5 | Same with bank account + issuer from discovery | Payout reaches test account; issuer name matches discovery list |
| 3 | Duplicate `depositorReference` | Repeat #1 with **same** `--reference` | Non-success or idempotent query; **no double payout** |
| 4 | Invalid phone/account | `--account=0990000000` or fake bank account | Terminal failure; loan stays undisburse |
| 5 | Insufficient cGrate float | Amount > merchant float | Clear error code; no loan completion |
| 6 | Query by `depositorReference` | `--query` on test command | Query returns meaningful status progression |
| 7 | Callback check | Monitor `POST /webhooks/gateways/cgrate` + `storage/logs/cgrate-uat-*.log` | Document whether callback arrives; polling still works without it |
| 8 | Wallet debit idempotency | Confirm same disbursement twice in FineEdge (simulate duplicate finalize) | Single debit; `metadata.finance_disbursement_posted_at` set once |
| 9 | Manual fallback | Fail gateway payout; use manual disburse modal | Loan disburses via manual path; treasury debit on selected account |

---

## Response code capture table

Copy this section per test run. Also log automatically in `storage/logs/cgrate-uat-*.log` when using artisan UAT commands.

| Test # | Step | Timestamp | Operation | depositorReference | responseCode | responseMessage | paymentId | Notes |
|--------|------|-----------|-----------|-------------------|--------------|-----------------|-----------|-------|
| 1 | Initiate | | processCashDeposit | | | | | |
| 1 | Query T+0 | | queryCustomerPayment | | | | | |
| 1 | Query T+30s | | queryCustomerPayment | | | | | |
| 1 | Query final | | queryCustomerPayment | | | | | |
| 2 | Initiate | | processCashDeposit | | | | | |
| 3 | Duplicate | | processCashDeposit | | | | | |
| 4 | Invalid acct | | processCashDeposit | | | | | |
| 5 | Low float | | processCashDeposit | | | | | |

**Community / EVDSpec hints (unverified for Konik merchant API):**

| Code | Possible meaning |
|------|------------------|
| `0` | Success / accepted |
| `1` | Insufficient balance (merchant float) |
| `7` | Invalid MSISDN |
| `8` | Process delay (pending) |
| `104` | Duplicate transaction reference |
| `105` | Reference not found (query) |
| `106` | Invalid transaction reference |
| `207`, `226` | Processed (collections query) |

---

## Bank issuerName mapping checklist

1. Run `php artisan cgrate:cash-deposit-issuers` (requires `CGRATE_ENABLED=true`).
2. Export FineEdge banks: `financial_institutions.name` where active.
3. For each bank used for disbursement, fill:

| financial_institutions.id | financial_institutions.name | cGrate issuerName (from discovery) | UAT payout OK? |
|---------------------------|------------------------------|-------------------------------------|----------------|
| | | | ☐ |

4. Add confirmed mappings to `config/cgrate.php` → `issuer_name_map` or a dedicated bank map (future).
5. Mobile mappings: `MTN_MONEY`→`MTN`, `AIRTEL_MONEY`→`Airtel`, `ZAMTEL_MONEY`→`Zamtel`.

---

## Callback verification checklist

- [ ] `CGRATE_CALLBACK_ENABLED=true` on UAT environment  
- [ ] Webhook URL reachable: `POST /webhooks/gateways/cgrate`  
- [ ] Run test disbursement; watch Laravel logs and `payment_gateway_logs`  
- [ ] Record whether callback payload references `depositorReference`  
- [ ] If no callback: confirm polling alone is acceptable for go-live  

---

## Polling verification checklist

- [ ] Gateway attempt moves `created` → `initiated` → `pending` after initiate  
- [ ] `QueryGatewayAttemptStatusJob` runs on schedule (or sync queue in dev)  
- [ ] Query returns pending codes until final `207`/`226`/`0`+`paymentId`  
- [ ] Loan stays `approved` + `disbursement_status=processing` until confirm  
- [ ] After confirm: `LoanDisbursementFinancePostingService` debits linked cGrate Wallet once  

---

## Finance posting verification checklist

- [ ] Linked cGrate Wallet balance decreases by `principal_amount` on confirm only  
- [ ] `loans.metadata.finance_disbursement_posted_at` present after confirm  
- [ ] `loans.disbursed_via_type` = `wallet`, `disbursed_via_id` = linked wallet  
- [ ] No debit on initiate or failed attempt  
- [ ] Manual disburse still debits admin-selected account (regression)  

---

## Go / no-go criteria

### Go (production disbursement)

- All matrix scenarios 1–6 passed on sandbox with documented codes  
- Issuer mapping complete for every live disbursement channel (MM + bank)  
- Query polling confirms payouts within agreed SLA (document max wait)  
- Duplicate reference handling understood and tested  
- Finance posting idempotency verified (scenario 8)  
- Manual fallback verified (scenario 9)  
- Operations runbook: float monitoring on cGrate Wallet  

### No-go

- `queryCustomerPayment(depositorReference)` returns `105`/`106` consistently  
- Initiate `0` incorrectly treated as completed by provider with no query path  
- Bank or MM payouts fail due to issuer name mismatch  
- Double debit or double loan completion observed  
- Callback required but not received and polling insufficient  

---

## Where to record results

| Artifact | Location |
|----------|----------|
| Automated UAT command logs | `storage/logs/cgrate-uat-YYYY-MM-DD.log` |
| Gateway attempt audit | Admin loan show → gateway disbursement attempts; `payment_gateway_attempts` / `payment_gateway_logs` tables |
| Manual capture table | This file (copy rows above) or internal UAT ticket |
| Issuer mapping | This file + `config/cgrate.php` `issuer_name_map` |
| Sign-off | Product / Finance / Engineering checklist in change ticket |

---

## Quick command reference

```bash
# Prerequisites in .env
# CGRATE_ENABLED=true
# CGRATE_BASE_URL=...
# CGRATE_USERNAME=...
# CGRATE_PASSWORD=...

# Discover issuer names
php artisan cgrate:cash-deposit-issuers
php artisan cgrate:cash-deposit-issuers --json

# Dry-run (no HTTP)
php artisan cgrate:test-cash-deposit --amount=5 --account=0978967132 --issuer=Airtel --dry-run

# Live provider test + query (requires confirmation or --force)
php artisan cgrate:test-cash-deposit --amount=5 --account=0978967132 --issuer=Airtel --query --force
```

**End-to-end loan UAT:** use admin loan show → **Disburse via cGrate** after gateway is active and wallet linked. Record attempt rows in the response capture table.
