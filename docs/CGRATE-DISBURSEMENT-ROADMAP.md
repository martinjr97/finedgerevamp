# cGrate Disbursement Roadmap

Roadmap date: 2026-07-02 (final)  
Gateway architecture: [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)  
Status: **Roadmap only — no implementation**

---

## Gateway Integration Does Not Replace Core Finance

Automated cGrate disbursements debit the **cGrate Wallet** through existing finance posting — the same pattern manual `LoanController::disburse()` uses.

```text
Gateway confirms payout → shared disburse service → debit cGrate Wallet → loan disbursed
```

---

## Phase D1: Manual remains default

`ManualDisbursementGateway` wraps existing `disburse()` — admin picks source bank/wallet. Zero production change.

---

## Phase D2: API discovery

cGrate payout API — **Needs confirmation** (not in ZAQA).

---

## Phase D3: cGrate automated disbursement

### Prerequisites

- cGrate Wallet exists and is linked on `payment_gateways` (same wallet as collections, or separate if configured)
- Shared disburse service extracted from `LoanController::disburse()`

### On gateway confirm

```text
Gateway attempt confirmed
  → LoanDisbursementService::completeAutomatedDisbursement(loan, gateway)
  → Shared finance posting: debit cGrate Wallet (Wallet::updateBalance debit)
  → Loan::applyDisbursementCompleted()
  → loans.disbursed_via_type = wallet, disbursed_via_id = cGrate Wallet id
```

**Gateway never debits wallet directly.** Uses same helper as manual `disburse()`.

### Checklist

- [ ] Extract debit logic from `LoanController::disburse()`
- [ ] Automated path debits gateway linked account (not admin-selected source)
- [ ] Manual `disburse()` unchanged
- [ ] Verify cGrate Wallet balance decreases on test disburse

---

## Phase D4: Gateway admin

Disbursement attempts on loan show; recheck-before-retry; link to cGrate Wallet balance (read-only).

---

## Account mapping

| Gateway | Operation | Account |
|---------|-----------|---------|
| cGrate | Collection | cGrate Wallet (credit) |
| cGrate | Disbursement | cGrate Wallet (debit) |

Operations must ensure cGrate Wallet has sufficient balance before automated disbursements.

---

## Related documents

- [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md) — §12 disbursement flow
- [FINANCIAL-TRANSACTION-ARCHITECTURE.md](./FINANCIAL-TRANSACTION-ARCHITECTURE.md)
- [CGRATE-LOAN-REPAYMENT-ROADMAP.md](./CGRATE-LOAN-REPAYMENT-ROADMAP.md) — P0b cGrate Wallet setup

---

## Automatic Gateway Disbursement on Approval

Implemented via `AutomaticLoanDisbursementService` — triggered after loan approval when the matching Gateway Routing row has **Automatic Processing** enabled.

### Enable from admin UI

1. **Payment Gateways** — ensure cGrate is Active with linked wallet and sufficient balance
2. **Gateway Routing** — open the matching disbursement row:
   - Wallet loans → **Wallet & Mobile Money Disbursements**
   - Bank loans → **Bank Account Disbursements**
3. Enable the route, select cGrate, turn on **Automatic Processing**, save
4. Status badge should show **Ready**

### Behaviour

- Approval never fails because auto-disburse cannot start
- If route is off or auto is off → loan approved, `disbursement_status` stays `pending`, manual disbursement required
- If auto is on and route ready → `payment_gateway_attempt` created, `disbursement_status=processing`, `DispatchGatewayDisbursementJob` queued
- If auto is on but gateway not ready (inactive, missing linked account, insufficient balance, invalid destination) → loan still approved, warning shown, manual fallback remains
- For **cGrate bank** disbursements, FineEdge requires an active `payment_gateway_destination_mappings` entry (gateway_key=`issuerName`). If missing (or `verification_required`), auto-disbursement is skipped and the loan remains `pending`.

### Configure destination mappings (Operations)

Admin route: `/admin/payment-gateway-destination-mappings`

1. **Sync cGrate Issuers** — calls `getAvailableCashDepositIssuers()` and caches the latest issuer list for reference (no SOAP duplication in the UI layer).
2. **Bank Mapping Coverage** — shows which FineEdge banks still need a mapping for the selected gateway/environment.
3. **Create mapping** — map FineEdge Bank → cGrate issuer value (e.g. `543` in UAT). The UI labels this as **cGrate issuerName** / **Gateway Value**; the database stores `gateway_key=issuerName`, `gateway_value=543`.
4. **UAT vs Production** — prefer environment-specific rows (`uat`, `production`) over Global when values differ between environments.

![Destination mappings administration](./images/payment-gateway-destination-mappings-admin.png)
- Wallet debited only after confirmed payout — not at approval or SOAP accept
