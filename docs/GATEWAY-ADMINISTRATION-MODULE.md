# Gateway Administration Module

Architecture date: 2026-07-02 (final)  
Gateway architecture: [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md)  
Status: **Roadmap / specification only — no implementation**

---

## Gateway Integration Does Not Replace Core Finance

Gateway Administration manages **providers and integration attempts**. Balances are **read from the existing financial module** — never maintained separately in gateway admin.

---

## 1. Purpose

Operations control for payment providers, including **linked financial account** configuration and read-only balance visibility from `banks` / `wallets`.

---

## 2. Financial account configuration (required)

### 2.1 Gateway detail — linked account

**Route:** `GET|PUT /admin/payment-gateways/{code}`

| Field | UI control |
|-------|------------|
| Financial account type | Dropdown: Bank / Wallet |
| Financial account | Searchable select — **only existing** `banks` or `wallets` |
| Active status | Read-only from linked account `is_active` |

**Example (cGrate):**

```text
Gateway: cGrate
Financial account type: Wallet
Financial account: cGrate Wallet
Current balance: ZMW 125,000.00  (read-only — from wallets table)
```

### 2.2 Rules

- Cannot create accounts from gateway admin — link to existing only
- Changing linked account requires `payment-gateways.manage` + audit log
- Separate collection vs disbursement accounts: **optional future** — default one account per gateway (cGrate Wallet for both)

### 2.3 Balance display

| Display | Source |
|---------|--------|
| Current balance | `Bank::balance` or `Wallet::balance` — read-only |
| Movement history | Link to existing financial reports / wallet detail / `repayments` filtered by `received_via_*` |

Gateway admin **must not** maintain its own balance table or edit balances.

---

## 3. Gateway registry (unchanged + account column)

List view adds:

| Column | Description |
|--------|-------------|
| Linked account | e.g. "Wallet: cGrate Wallet" |
| Account balance | Read-only snapshot |

---

## 4. Other features (summary)

| Feature | Scope |
|---------|-------|
| Enable/disable / maintenance | `payment_gateways.status` |
| Priority / routing | Per payment method |
| Health dashboard | Provider uptime, errors |
| Gateway logs | Callbacks, polls |
| Attempt timeline | On repayment/loan show |
| Recheck / retry | Provider status only |
| Analytics | Success rate, latency |
| Credentials | Env metadata only — no plaintext |

**Not included:** gateway-owned balances, settlement import, float management screens (use existing Wallets admin).

---

## 5. Ops workflow: cGrate Wallet setup

1. Finance admin creates **cGrate Wallet** via `/admin/wallets` (existing UI)
2. Ops records opening balance / loads float via existing balance management
3. Gateway admin links cGrate gateway → cGrate Wallet
4. Enable cGrate gateway for integrated collections
5. Monitor cGrate Wallet balance via existing financial reports

When Operations loads money into cGrate externally, they update cGrate Wallet through existing financial tools — not through gateway admin.

---

## 6. Manual flows when gateways disabled

Unchanged. Manual repayments credit admin-selected accounts; manual disbursements debit admin-selected sources. No gateway linked account required.

---

## 7. Implementation phases

| Phase | Features |
|-------|----------|
| GA-1 | Gateway list + linked account selector + read-only balance |
| GA-2 | Attempt panel on repayment/loan show |
| GA-3 | Recheck, retry, stuck queue |
| GA-4 | Analytics |

---

## 8. Related documents

- [PAYMENT-GATEWAY-ARCHITECTURE.md](./PAYMENT-GATEWAY-ARCHITECTURE.md) — §4 gateway accounts
- [FINANCIAL-TRANSACTION-ARCHITECTURE.md](./FINANCIAL-TRANSACTION-ARCHITECTURE.md)
- [CGRATE-LOAN-REPAYMENT-ROADMAP.md](./CGRATE-LOAN-REPAYMENT-ROADMAP.md)

---

## 9. Automatic Gateway Disbursement on Approval

Gateway Routing **Automatic Processing** (`auto_process`) controls whether approving a loan triggers gateway disbursement automatically.

| UI location | Field | Purpose |
|-------------|-------|---------|
| Gateway Routing → Wallet Disbursements | Enabled + Automatic Processing | Auto-disburse wallet/mobile loans on approval |
| Gateway Routing → Bank Disbursements | Enabled + Automatic Processing | Auto-disburse bank loans on approval |

### Operator checklist

1. Link cGrate to cGrate Wallet on Payment Gateways detail
2. Enable and assign gateway on the correct routing row
3. Turn on Automatic Processing when ops wants approve-and-disburse in one step
4. Confirm status badge is **Ready** before relying on auto-disburse

### Failure handling

- Approval always succeeds
- Auto-disburse skip shows a warning; manual **Record Disbursement** or **Disburse via cGrate** remains available
- Gateway attempt history appears on loan show when auto-disburse initiates
