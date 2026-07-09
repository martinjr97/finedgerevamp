# cGrate Integration Analysis

Analysis date: 2026-07-02 (final)  
Gateway architecture: [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)  
Status: **Analysis only — no implementation**

---

## Gateway Integration Does Not Replace Core Finance

cGrate connects to an existing **cGrate Wallet** in the financial module. On collection confirm:

```text
Gateway confirmed → finalizeIntegratedRepayment() → credit cGrate Wallet → loan updated
```

Gateway code never calls `updateBalance()`. Shared finance posting (extracted from manual `approve()`) credits the wallet.

---

## 1. Summary

| Item | Detail |
|------|--------|
| ZAQA reference | Production SOAP collections |
| FineEdge account | **cGrate Wallet** (existing `wallets` model) |
| Gateway registry | `payment_gateways` → `financial_account_type=wallet`, `financial_account_id` = cGrate Wallet |
| Business hook | `finalizeIntegratedRepayment()` + shared finance posting |

---

## 2. cGrate Wallet setup (ops prerequisite)

1. Create wallet named e.g. "cGrate Wallet" via `/admin/wallets`
2. Set opening balance when Operations loads float into cGrate
3. Link cGrate gateway to this wallet in gateway admin
4. Future balance changes via existing wallet management only

---

## 3. ZAQA → FineEdge mapping

| ZAQA | FineEdge |
|------|----------|
| Payment confirmed | Gateway attempt confirmed |
| Invoice paid | Loan finalize + **credit cGrate Wallet** |
| `PaymentAttempt` | `payment_gateway_attempts` |
| N/A | `payment_gateways.financial_account_*` → cGrate Wallet |

---

## 4. Safeguard — reuse existing paths

| Action | Existing code | Gateway triggers |
|--------|---------------|------------------|
| Credit wallet | `Wallet::updateBalance(credit)` via approve | Shared helper from `finalizeIntegratedRepayment()` |
| Loan allocation | `applyRepaymentToLoans()` | Same |
| Ledger | `LoanRepaymentLedgerService` | Same |
| `received_via_*` | Set on `repayments` | `wallet` + cGrate Wallet id |

---

## 5. Admin repayment gateway collection

Admin repayment creation (`/admin/customers/{customer}/repayments/create`) no longer uses `Channel.is_repayment_integrated` or an Auto/Manual submission toggle.

| Input | Source of truth |
|-------|-----------------|
| Payment method | Repayment **channel** (mobile wallet, bank, cash) |
| Whether collection runs | **Gateway Routing** (`wallet_collection` / `bank_collection`) |
| Gateway used | Route-assigned gateway (e.g. cGrate when route is ready) |
| Fallback | `payment_gateway_routes.fallback_to_manual` |

When the wallet collection route is ready, admin submit creates a `processing` repayment, a `payment_gateway_attempt`, and queues `DispatchGatewayCollectionJob`. cGrate `collect()` is **mobile money only** — bank collection routes fall back to manual when the provider does not support bank collection (cGrate included).

Loan balance and cGrate Wallet credit occur only after gateway confirmation via `finalizeIntegratedRepayment()` — same as customer-integrated repayments.

The customer portal repayment flow is unchanged in this phase.

---

## 6. Admin repayment show — polling, recheck, and manual reconciliation

After admin gateway collection is initiated, the repayment remains `processing` while `QueryGatewayAttemptStatusJob` polls cGrate. The admin show page must **not** present manual provider confirmation as the primary workflow during this window.

### Runtime behaviour

1. `DispatchGatewayCollectionJob` sends the collection prompt and marks the attempt `pending`.
2. `QueryGatewayAttemptStatusJob` queries `queryCustomerPayment` on the configured poll interval.
3. If `initiated_at + CGRATE_PAYMENT_EXPIRY_MINUTES` passes without a terminal response, the attempt is marked `expired` and the repayment `failed`.
4. On `confirmed`, `finalizeConfirmedAttempt()` applies loans and credits the linked cGrate Wallet (or sets `requires_finance_reconciliation` when no linked account).

### Recommended env (ops)

```env
CGRATE_POLL_INTERVAL_SECONDS=30
CGRATE_PAYMENT_EXPIRY_MINUTES=10
```

Defaults in `config/cgrate.php` remain unchanged; production tuning is via `.env` only.

### On-demand recheck and manual reconciliation

Gateway repayments **normally auto-finalize** via `QueryGatewayAttemptStatusJob` (see Runtime behaviour above). Admin recheck is a **query-only diagnostic** — it does not mutate balances.

`POST /admin/repayments/{repayment}/gateway-recheck`

1. Queries cGrate via `queryCustomerPayment` using `provider_reference ?? internal_reference` (same reference as `processCustomerPayment`).
2. Compares local repayment/attempt status with the gateway response.
3. Flashes `gateway_recheck_result` to a modal — no loan or finance account changes.

When the gateway is confirmed but FineEdge is not yet synchronized, the modal offers **Apply Gateway Synchronization**:

`POST /admin/repayments/{repayment}/gateway-recheck/apply` (requires `repayments.process` + admin note)

This calls the same `finalizeConfirmedAttempt` path as automatic polling. It is **idempotent** — repeated applies do not double-credit wallet/bank or double-apply to loans.

When the gateway reports failure, admins may **Mark as Failed** (`gateway-recheck/mark-failed`) with a note. This does not update loan balances.

**Manual repayments** (no gateway attempt) use the **Manual Repayment Approval** panel and modal. They are permission-protected (`repayments.approve`) and separate from gateway flows.

Legacy route `apply-gateway-confirmation` remains as a deprecated alias without a note field.

### Manual reconciliation warning

Manual provider confirmation remains available in a modal for exceptional gateway cases. It calls the existing `updateProcessingStatus()` path, which finalizes loans but **does not** credit wallet/bank — same known gap as before. Use **Apply Gateway Synchronization** when the gateway attempt is already `confirmed`.

---

## 7. Next step

[P1 in CGRATE-LOAN-REPAYMENT-ROADMAP.md](./CGRATE-LOAN-REPAYMENT-ROADMAP.md): cGrate Wallet setup + gateway link + shared finance posting.

---

## Related documents

- [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)
- [FINANCIAL-TRANSACTION-ARCHITECTURE.md](./FINANCIAL-TRANSACTION-ARCHITECTURE.md)
- [GATEWAY-ADMINISTRATION-MODULE.md](./GATEWAY-ADMINISTRATION-MODULE.md)
