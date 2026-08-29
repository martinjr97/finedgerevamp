# M2 — MARK-001 Product Configuration Gap Assessment

**Date:** 2026-08-22  
**Classification:** `MATCH_WITH_CONFIGURATION`

---

## Current MARK-001 state

| Field | Value |
|-------|-------|
| code | MARK-001 |
| category | marketeer |
| tenure_months | 1 |
| max_amount | 500,000 |
| rules | `{}` (empty) |
| loan_rate_types | **None seeded** |

Reference migration **maps only** — does not overwrite product fields.

---

## Legacy behaviour vs target

| Behaviour | Legacy | Target MARK-001 | Gap |
|-----------|--------|-----------------|-----|
| Term | 4 weeks (hardcoded) | tenure_months = 1 | **CONFIG_ONLY** — approximate |
| Repayment frequency | Weekly | Supported via `LoanRateType.accrual_period=weekly` | **CONFIG_ONLY** — no rate type seeded |
| Pricing formula | `principal + (principal × weekly_rate% × 4)` | `LoanPricingService` weekly multiplier | **ALREADY_SUPPORTED** if rate configured |
| Weeks 1–3 interest only | Yes | Schedule generation logic | **CODE_REQUIRED** — no legacy-style 4-week schedule generator verified for MARK-001 |
| Week 4 principal + interest | Yes | Standard amortisation | **CODE_REQUIRED** |
| Market-level weekly_rate | `markets.weekly_rate` | `markets.loan_rate_type_id` or loan-level `weekly_rate` | **CONFIG_ONLY** |
| Fixed/non-accrual balance | Yes | `LoanBalanceService` fixed path | **ALREADY_SUPPORTED** |

---

## Gap summary

| Gap | Classification | Impact |
|-----|----------------|--------|
| No MRKT-WKLY rate type on MARK-001 | CONFIG_ONLY | New loans need rate type + weekly rate |
| Empty product rules | CONFIG_ONLY | Migration metadata stores legacy weekly_rate on market maps |
| 4-week interest-only schedule pattern | CODE_REQUIRED | Historical migration uses replay engine, not live origination |
| MARK-001 not in LoanProductSeeder | CONFIG_ONLY | Exists in dev DB (id 8); migration maps it |

---

## Historical migration vs new origination

| Concern | Status |
|---------|--------|
| Migrate 71 legacy Marketeer loans + schedules | **MIGRATION_ONLY** — replay engine handles |
| Originate NEW Marketeer loan post-migration | **MARKETEER_PRODUCT_CONFIGURATION_REQUIRED** |

### New loan test assessment

Revamp `LoanPricingService` supports weekly multiplier interest:
```text
interest = principal × weekly_rate × ceil(termDays / 7)
```

Legacy uses flat weekly charge × 4 with balloon principal in week 4. These are **not identical** without custom schedule rules.

**Verdict:** New Marketeer loan origination requires:
1. Seed `LoanRateType` with `accrual_period=weekly` on MARK-001
2. Configure market `loan_rate_type_id` or per-loan `weekly_rate`
3. Implement or verify 4-week schedule generator matching legacy pattern (weeks 1–3 interest, week 4 + principal)

Do **not** modify historical migration replay to compensate.

---

## Recommended post-migration work (not in scope)

1. Add `MRKT-WKLY` rate type to MARK-001 (10% default matching legacy markets)
2. Link migrated markets to rate type
3. Add feature test for new 4-week Marketeer loan origination
4. Document schedule generation rule in product config

Migration reference promote does **not** change MARK-001 pricing.
