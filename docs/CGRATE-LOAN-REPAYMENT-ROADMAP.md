# cGrate Loan Repayment Roadmap

Roadmap date: 2026-07-02 (final)  
Gateway architecture: [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)  
Status: **Roadmap only — no implementation**

---

## Gateway Integration Does Not Replace Core Finance

Integrated cGrate collections credit the **cGrate Wallet** through existing finance posting — the same pattern manual `approve()` uses for bank/wallet credit.

```text
cGrate confirms → finalizeIntegratedRepayment() → credit cGrate Wallet → loan updated
```

---

## Phase P0b: Financial prerequisite (ops)

| Step | Action |
|------|--------|
| 1 | Create **cGrate Wallet** via existing `/admin/wallets` |
| 2 | Set opening balance (Operations loads cGrate float) |
| 3 | Document wallet ID for gateway seed |

No new account system — standard wallet record.

---

## Phase P1: Gateway + cGrate collections

### P1.1 Database

**`payment_gateways`:**

| Field | cGrate seed value |
|-------|-------------------|
| `code` | `cgrate` |
| `financial_account_type` | `wallet` |
| `financial_account_id` | cGrate Wallet id |
| `capabilities` | collections, mobile_money, callback, polling |
| `certification` | `production_certified` |

**`payment_gateway_attempts`:** `repayment_id`, refs, status, payloads.

### P1.2 Shared finance posting (critical)

| Task | Detail |
|------|--------|
| Extract credit logic | From `Admin\RepaymentController::approve()` lines: `updateBalance(credit)`, `received_via_*` |
| New helper | e.g. `RepaymentFinancePostingService::creditReceivedAccount(Repayment, type, id, amount)` |
| Extend `finalizeIntegratedRepayment()` | After provider refs, call helper with gateway's linked cGrate Wallet |
| Rule | Gateway listener never calls `updateBalance()` — only passes account from `payment_gateways` config |

### P1.3 Gateway layer

- `CGratePaymentGateway`, jobs, webhook (port ZAQA)
- `GatewayIntegrationService` — orchestration only
- On confirm → `FinalizeRepaymentListener` → `finalizeIntegratedRepayment()`

### P1.4 Manual flows

Unchanged when gateways off. `approve()` continues to credit admin-selected account.

### P1.5 Checklist

- [ ] cGrate Wallet created (ops)
- [ ] `payment_gateways` with financial account link
- [ ] Shared finance posting extracted and wired
- [ ] `finalizeIntegratedRepayment()` credits cGrate Wallet
- [ ] `received_via_type/id` set on integrated repayments
- [ ] Loan allocation unchanged
- [ ] Manual approve unchanged
- [ ] Gateway never calls `updateBalance()` directly

---

## Phase P2: Gateway admin

- Linked account selector + read-only balance ([GATEWAY-ADMINISTRATION-MODULE.md](./GATEWAY-ADMINISTRATION-MODULE.md))
- Attempt timeline, recheck, retry

---

## Phase P3+: Second provider

Different gateway → different mapped bank/wallet (e.g. Stanbic for CyberSource).

---

## Related documents

- [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md) — §11 collection flow
- [FINANCIAL-TRANSACTION-ARCHITECTURE.md](./FINANCIAL-TRANSACTION-ARCHITECTURE.md)
