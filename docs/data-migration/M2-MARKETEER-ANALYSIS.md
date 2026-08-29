# M2 — Legacy Marketeer / Marketize Forensic Analysis

**Date:** 2026-08-22  
**Legacy:** `/var/www/personal/finedge` (read-only)  
**Status:** Migration paused — no `--promote` executed

---

## Identification rule (authoritative)

A legacy record is **Marketeer** when **either**:

1. `customers.is_marketize_customer = true` (primary runtime flag), **or**
2. `clients.product_type = 'marketize_based'` (product bucket client)

In current data both signals align for all 35 customers on client **36 "Marketize Loans"**.

`customers.market_id` is **required** for all 35 Marketeer customers (0 null).

Repayment routing also checks `is_marketize_customer` before `product_type` (`LoanRepaymentController::executeMarketizeLoanRepayment`).

---

## Population counts

| Metric | Count |
|--------|------:|
| Total Marketeer customers | **35** |
| Active Marketeer customers (status 301 loan) | **24** |
| Historical-only (settled/no active loan) | **11** |
| Customers with no loan ever | **3** |
| Legacy markets | **2** |
| Marketeer loans (all statuses) | **71** |
| Active Marketeer loans (301) | **24** |
| `marketize_loan_schedules` rows | **284** (71 loans × 4 weeks) |
| Multi-product customers (Marketeer + other) | **0** |

---

## Legacy markets

| Legacy id | Name | Weekly rate | Customers | Active customers | Loans | Active loans |
|----------:|------|------------:|----------:|-----------------:|------:|-------------:|
| 1 | Lilanda Market | 10% | 29 | 19 | 66 | 19 |
| 2 | Mwamba Luchembe Market | 10% | 6 | 5 | 5 | 5 |

### What a Market controls (legacy)

| Role | Source |
|------|--------|
| **Pricing** | `markets.weekly_rate` → used in customer self-service flow |
| **Geographic grouping** | `markets.name`, `location` |
| **Collection context** | Market manager via `market_manager_id` → `kyc_verifiers` |
| **Max loan (admin path only)** | `markets.max_loan_amount`, `max_loan_period` — **not enforced** in customer self-service flow |

Customer self-service caps loan amount on `customers.gross_salary` (repurposed as max), not `markets.max_loan_amount`.

---

## Legacy client for Marketeer

| Legacy client id | Name | product_type | Customers | Classification |
|-----------------|------|--------------|----------:|----------------|
| 36 | Marketize Loans | marketize_based | 35 | **LEGACY_MARKETEER_PLACEHOLDER** |

This client is a **synthetic product bucket** (reg `MKT-2025-001`), not a real employer. It must **not** become a revamp `Company`.

> **Note:** A prior pilot run incorrectly mapped client 36 → target company 9. New migration logic skips this client; `company_id` remains null for Marketeer customers regardless.

---

## Pricing formula (customer self-service — primary path)

From `MarketizedLoanFlowController.php`:

```text
weeklyRate = markets.weekly_rate (fallback 10%)
weeklyCharge = round(principal × weeklyRate / 100, 2)
weeks = 4  (hardcoded)
totalRepayment = round(principal + weeklyCharge × weeks, 2)
loan_amount = ceil(totalRepayment)
obtained_amount = principal
```

**Schedule structure (4 weeks):**

| Week | Amount |
|------|--------|
| 1–3 | `weeklyCharge` (interest only) |
| 4 | `weeklyCharge + principal` |

Verified against 10 active loans — formula matches stored `loan_amount` and schedule totals.

**Admin alternate path** (`MarketizeLoanController`) uses monthly-style interest and **does not create schedules** — not used for current active portfolio.

---

## Term and repayment frequency

| Attribute | Value |
|-----------|-------|
| Term | **4 weeks** (hardcoded in customer flow) |
| Frequency | **Weekly** |
| Source | Hardcoded in controller; rate from market |

---

## Schedule behaviour (`marketize_loan_schedules`)

| Column | Purpose |
|--------|---------|
| `week_number` | Installment sequence (1–4) |
| `due_date` | Weekly due date from disbursement |
| `weekly_amount` | Expected installment |
| `paid_amount` | Amount applied to this week |
| `status` | `pending`, `paid`, `partial` (`overdue` never written by cron) |

Schedules are **authoritative for repayment allocation** (FIFO by week). Generated at loan origination for customer-flow loans.

---

## Repayment allocation (legacy)

`LoanRepaymentController::executeMarketizeLoanRepayment`:

1. **Full payment** (≥ outstanding): all schedules → paid; loan → status 300; account balance 0
2. **Partial payment**: FIFO by `week_number`; full week → paid; partial → partial status
3. **Principal/interest split** stored proportionally: `interest = loan_amount - obtained_amount`
4. No cross-loan allocation for Marketeer (single active loan guard at origination)

---

## Balance truth

| Source | Use |
|--------|-----|
| **`LoanBalanceService`** | **Authoritative** — fixed/non-accrual: `max(0, loan_amount - repaid_amount)` |
| `marketize_loan_schedules` | Allocation reference; sum of remaining = should match effective balance |
| `loans_accounts.balance` | Customer-level aggregate — **not per-loan truth** |

---

## Operational payment flow

| Stage | Mechanism |
|-------|-----------|
| Disbursement | Mobile money (Airtel/MTN/Zamtel) via Lipila/Kazang after approval |
| Repayment | Customer-initiated mobile money collection → FIFO schedule allocation |
| Cash/agent | No dedicated Marketeer cash path |
| Market role | Pricing + reporting; not a ledger entity |

---

## Data quality

| Check | Result | Classification |
|-------|--------|----------------|
| Marketeer customer without market_id | 0 | — |
| Invalid market_id reference | 0 | — |
| Marketize loan without schedule | 0 (71/71 have schedules) | — |
| Multi-product customer | 0 | — |
| Erroneous client→company pilot map | 1 (client 36→co 9) | **RULE_REQUIRED** — ignored by new logic |

---

## Key legacy files

| Area | Path |
|------|------|
| Customer loan flow | `app/Http/Controllers/MarketizedLoanFlowController.php` |
| Repayment allocation | `app/Http/Controllers/LoanRepaymentController.php` |
| Balance service | `app/Services/LoanBalanceService.php` |
| Market model | `app/Models/Market.php` |
| Schedules | `app/Models/MarketizeLoanSchedule.php` |
