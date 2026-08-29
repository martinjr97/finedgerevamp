# M2 — Customer Identity Resolution

**Date:** 2026-08-22  
**Method:** Migration mapping only — legacy DB unchanged

---

## Duplicate NRC groups found: **2**

| NRC | Legacy users | Classification |
|-----|--------------|----------------|
| 730989/11/1 | 14, 19 | SAME_PERSON_KEEP_SEPARATE_HISTORY_MAP_ONE_TARGET |
| 631351/11/1 | 126, 127 | SAME_PERSON_KEEP_SEPARATE_HISTORY_MAP_ONE_TARGET |

---

## Users 14 / 19 analysis

| Field | User 14 | User 19 |
|-------|---------|---------|
| Name | Christopher Banda | Christopher Banda |
| NRC | 730989/11/1 | 730989/11/1 |
| Employee # | 0000021 | Character-based001 |
| Phone | 260968425000 | 260977425000 |
| Email | didcottcb@hotmail.com | christopher_banda@aol.com |
| Client | 6 (Character-based-2) | 7 (Character-based-3) |
| Status | 600 (active) | 606 (suspended) |
| Loans | 26 (0 active) | 26 (0 active, separate IDs) |
| Repayments | 4 | 21 |
| Created | 2021-04-08 | 2021-05-21 |

**Conclusion:** Same person with duplicate legacy accounts. User **14** is primary (matches target customer 7 biodata). User **19** is suspended duplicate with separate financial history.

**Resolution:** Both map to target customer **7**. Legacy loan/repayment traceability preserved via separate `legacy_user_id` on each loan map.

---

## Users 126 / 127 analysis

| Field | User 126 | User 127 |
|-------|----------|----------|
| Name | Mundia Sekeli | Mundia Sekeli |
| NRC | 631351/11/1 | 631351/11/1 |
| Employee # | Chris057 | Chris058 |
| Phone | 260969018542 | 260977873755 |
| Status | 604 (blocked) | 604 (blocked) |
| Loans | **0** | **13** |
| Repayments | **0** | **9** |

**Conclusion:** User **127** is primary (all financial history). User **126** is obsolete empty duplicate.

**Resolution:** On customer promote, user 127 creates target customer; user 126 maps as alias to same target.

---

## True customer migration count

| Metric | Count |
|--------|------:|
| Legacy customer rows | 1,936 |
| Unique target customers after promote | **1,934** |
| Shared targets (duplicate pairs) | 2 |

---

## Commands

```bash
# Preview
php artisan migration:identity-resolve

# Apply approved resolutions to migration_entity_maps
php artisan migration:identity-resolve --apply
```

Output: `docs/data-migration/tools/customer-identity-resolutions-applied.json`

---

## Map changes applied

| Entity | Legacy id | Action |
|--------|-----------|--------|
| Customer | 14 | Primary → target 7 (`identity_resolution_primary`) |
| Customer | 19 | Alias → target 7 (`identity_resolution_alias`) |
| Company | 36 | Annotated `OBSOLETE_IGNORED` (superseded pilot map) |

Users 126/127: resolution registered; maps created on customer promote.

---

## Financial history protection

Both legacy user IDs retain independent:
- `legacy_user_id` on loan entity maps
- `legacy_customer_id` in customer metadata
- Separate loan histories (no merge in legacy DB)

Multiple legacy identities → one target customer is supported via `migration_entity_maps` with `role: primary|alias` metadata.
