# M2 — Marketeer Target Architecture Design

**Date:** 2026-08-22  
**Principle:** Marketeer is **not company-based**. Legacy `clients` are classification signals only.

---

## Target hierarchy

```text
loan_products.category = 'marketeer'
        ↓
LoanProduct (MARK-001)
        ↓
CustomerGroup (MRKT-LEGACY)          ← new migration default group
        ↓
Market (markets table)
        ↓
Customer + MarketeerCustomerDetail
        ↓
Loan (loan_product_id = MARK-001)
```

---

## What exists in revamp (no schema change required)

| Layer | Implementation |
|-------|----------------|
| **Product type** | `loan_products.category` ENUM includes `'marketeer'` |
| **Product** | `MARK-001` — id 8, tenure 1 month, max 500k, rules `[]` |
| **Group** | `customer_groups` with `loan_product_id` FK |
| **Market** | `markets` table (province, district, contact, optional `loan_rate_type_id`) |
| **Customer → Market** | `marketeer_customer_details.market_id` (1:1 with customer) |
| **Customer → Group** | `customers.customer_group_id` (nullable) |
| **Loan → Product** | `loans.loan_product_id` — **authoritative** |

Markets are **not** FK children of groups in revamp schema. Group and Market are parallel dimensions linked through the customer:

```text
CustomerGroup ←── customer_group_id ── Customer ── marketeer_customer_details ──→ Market
```

This matches existing admin UI (`Admin/CustomerController` requires `market_id` for marketeer category).

---

## MARK-001 current configuration

| Field | Value |
|-------|-------|
| id | 8 |
| code | MARK-001 |
| name | Marketeer Loan |
| category | marketeer |
| tenure_months | 1 |
| max_amount | 500,000 |
| rules | `{}` (empty — weekly rate lives at market/loan level in legacy) |

**Classification:** `MATCH_WITH_CONFIGURATION` — product exists; weekly rate types not yet seeded for migration (legacy rate preserved in entity map metadata).

---

## Business rules (locked)

### Company

```text
Marketeer customer → company_id = NULL
```

Legacy client 36 → `SKIP_MARKETEER_PLACEHOLDER` (never CREATE company).

### Product assignment

| Layer | Rule |
|-------|------|
| **Loan** | `loan_product_id = MARK-001` — authoritative |
| **Customer** | `loan_product_id = MARK-001` default (schema required); customer may hold other products historically in future — current data has 0 multi-product |
| **Group** | Default `MRKT-LEGACY` group for migrated traders |
| **Market** | Via `MarketeerCustomerDetail` from legacy `customers.market_id` |

### Reference-data sequence

```text
1. Products (MARK-001 map)
2. Marketeer group (MRKT-LEGACY)
3. Markets (legacy markets → target markets)
4. Customers
5. Active loans
6. Repayments (replay unchanged)
```

Command: `php artisan migration:reference-data --only=marketeer --dry-run`

---

## Schema changes

**`NO_SCHEMA_CHANGE`** — existing revamp tables support the design.

Migration adds:
- Entity maps: `customer_group`, `market`
- `MarketeerCustomerDetail` rows on customer promote
- No new tables

Optional future enhancement (not required for migration):
- Seed `LoanRateType` MRKT-WKLY with `accrual_period=weekly` on MARK-001
- Link markets to rate type

---

## Repayment migration compatibility

`MarketizeReplayStrategy` unchanged — uses legacy financial evidence (schedules + repayments), not target market/group structure. Target relationship changes do not alter replay math.

---

## Promotion gates (Marketeer-specific)

Reference-data must promote markets before customers:

- Customer dry-run reports `marketeer_market_pending` until market entity maps exist
- Promote blocked if Marketeer customer has no resolvable market map
