# M2 — Marketeer Migration Mapping

**Date:** 2026-08-22

---

## Legacy client handling

| legacy_client_id | name | product_type | customers | loans | classification | action |
|-----------------|------|--------------|----------:|------:|----------------|--------|
| 36 | Marketize Loans | marketize_based | 35 | 71 | LEGACY_MARKETEER_PLACEHOLDER | **SKIP_MARKETEER_PLACEHOLDER** |

No real independent companies identified among Marketeer-related clients.

---

## Legacy market → target mapping

| legacy_market_id | name | customers | active cust | loans | active loans | target group | target market | action |
|-----------------|------|----------:|------------:|------:|-------------:|--------------|---------------|--------|
| 1 | Lilanda Market | 29 | 19 | 66 | 19 | MRKT-LEGACY | MRKT-LEG-1 | **CREATE** |
| 2 | Mwamba Luchembe Market | 6 | 5 | 5 | 5 | MRKT-LEGACY | MRKT-LEG-2 | **CREATE** |

On `--promote`: create group + markets; store entity maps. Existing target markets matched by code `MRKT-LEG-{id}` or normalized name first.

---

## Customer migration rule

For each Marketeer customer (`is_marketize_customer` or `marketize_based` client):

1. Source biodata from `users` + `customers`
2. `company_id = null` (ignore legacy client 36)
3. `loan_product_id = MARK-001`
4. `customer_group_id = MRKT-LEGACY` (after reference promote)
5. Create `MarketeerCustomerDetail` with mapped `market_id`
6. Preserve `legacy_market_id` in metadata
7. `monthly_income` ← legacy `gross_salary` (max loan cap field)
8. Missing market map → `MANUAL_REVIEW` / blocked on promote

---

## Active loan migration (24 loans)

All 24 active Marketeer loans:

- Map to `MARK-001` via `LegacyProductMapper`
- Require customer entity map (blocked until customer promote)
- Do **not** use `clients.product_type` for company mapping
- Replay strategy: `MarketizeReplayStrategy` (unchanged)

Sample active loans: 18064, 17795, 18237 … (19 Lilanda, 5 Mwamba Luchembe).

---

## Dry-run totals (post-implementation)

### Reference data

```json
"companies": {
  "MATCHED_EXISTING": 4,
  "WOULD_CREATE": 29,
  "SKIP_GOVERNMENT_PLACEHOLDER": 1,
  "SKIP_MARKETEER_PLACEHOLDER": 1,
  "SKIP_UNUSED": 3
},
"marketeer": {
  "groups": { "WOULD_CREATE": 1 },
  "markets": { "WOULD_CREATE": 2 }
}
```

Client 36 no longer counts as MATCH_EXISTING company (was 5, now 4 + 1 skip).

### Customers

```json
"marketeer_customers": 35,
"marketeer_market_mapped": 0,
"marketeer_market_pending": 35,
"marketeer_missing_market": 0,
"marketeer_incorrect_company_link": 35
```

`marketeer_market_pending` → 0 after `migration:reference-data --promote` creates markets.  
`marketeer_incorrect_company_link` flags obsolete pilot map client 36→company 9 (ignored at runtime).

---

## Existing target data protection

| Entity | Policy |
|--------|--------|
| MARK-001 | MATCH → MAP; never overwrite rules/name |
| MRKT-LEGACY group | Match by code first |
| Markets | Match by `MRKT-LEG-{id}` or name; never overwrite config |

---

## Implementation files

| File | Purpose |
|------|---------|
| `MarketeerClassifier.php` | Identity + placeholder detection |
| `MarketMatcher.php` | Market match by code/name |
| `MarketeerReferenceMigrator.php` | Group + market reference migration |
| `ReferenceDataMigrator.php` | SKIP_MARKETEER_PLACEHOLDER + marketeer phase |
| `CustomerMigrator.php` | Marketeer stats + MarketeerCustomerDetail |
