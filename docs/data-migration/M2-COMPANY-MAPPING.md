# M2 — Company Mapping Rules

## Source

Legacy employers: `finedge.clients` (38 total)

## Classification actions

| Action | Count | Meaning |
|--------|------:|---------|
| `MATCH_EXISTING` | 5 | Entity map or target match; no create |
| `CREATE` | 29 | MOU employers with customers; will create on promote |
| `SKIP_GOVERNMENT_PLACEHOLDER` | 1 | Client 8 "GRZ" |
| `SKIP_MARKETEER_PLACEHOLDER` | 1 | Client 36 "Marketize Loans" — not a company |
| `SKIP_UNUSED` | 3 | Clients 25, 31, 37 — zero customers |
| `MANUAL_REVIEW` | 0 | — |

**Invariant:** 5 + 29 + 1 + 3 = **38** ✓

Prior M2 incorrectly reported 33 would-create + 1 gov (= 39) by counting unused clients as would-create.

## Do NOT create company when

- Client is **GRZ Government placeholder** (client id 8) — customers use GOV-001 with `company_id = null`
- Client has **zero customers** (`SKIP_UNUSED`)

## Match before create

1. Explicit mapping (`migration_entity_maps` or `settings.legacy_client_id`)
2. Registration number
3. Normalized exact name
4. Legacy code `LEG-{id}`

## On match

```text
MATCH_EXISTING → store migration_entity_maps → SKIP CREATE
```

Target company fields are **not** overwritten from legacy.

## Dry-run result (post-audit)

| Outcome | Count |
|---------|------:|
| MATCHED_EXISTING | 4 |
| WOULD_CREATE | 29 |
| SKIP_GOVERNMENT_PLACEHOLDER | 1 |
| SKIP_MARKETEER_PLACEHOLDER | 1 |
| SKIP_UNUSED | 3 |

**Invariant:** 4 + 29 + 1 + 1 + 3 = **38** ✓

See also: `M2-MARKETEER-MAPPING.md` (Marketeer client 36 must not become a company).
