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

## 5. Next step

[P1 in CGRATE-LOAN-REPAYMENT-ROADMAP.md](./CGRATE-LOAN-REPAYMENT-ROADMAP.md): cGrate Wallet setup + gateway link + shared finance posting.

---

## Related documents

- [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)
- [FINANCIAL-TRANSACTION-ARCHITECTURE.md](./FINANCIAL-TRANSACTION-ARCHITECTURE.md)
- [GATEWAY-ADMINISTRATION-MODULE.md](./GATEWAY-ADMINISTRATION-MODULE.md)
