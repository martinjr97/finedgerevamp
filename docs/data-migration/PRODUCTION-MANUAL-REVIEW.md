# Production Manual Review Cases

Records excluded from automatic migration. Resolve before or after cutover as appropriate.

## Active loan manual review

| Legacy user | Legacy loans | Reason |
|---:|---|---|
| 1835 | 16969, 17617 | Ambiguous repayment allocation — do not guess |

## Repayment manual review

| Legacy repayment IDs | Related loans | Class |
|---|---|---|
| 28303, 28308 | 16969, 17617 | C_AMBIGUOUS |

## Customer manual review

| Legacy client | Customers | Note |
|---:|---:|---|
| 1 (Finedge Stuff) | 2 | Internal/test — no auto company |
| 9 (Finedge Test) | 2 | Internal/test — no auto company |

## Identity aliases (resolved — map, do not create duplicate targets)

| NRC | Primary user | Alias user | Target |
|---|---|---:|---|
| 730989/11/1 | 14 | 19 | Same customer (7 locally) |
| 631351/11/1 | 127 | 126 | Same customer (62 locally) |

## Obsolete pilot company maps (never link customers)

| Legacy client | Reason |
|---:|---|
| 36 | Marketeer placeholder |
| 6, 7 | Character agent placeholders |
| 2 | Vendor character bucket |

## Post-migration follow-ups from local rehearsal

| Issue | Count | Priority |
|---|---:|---|
| Government customers with unexpected `company_id` | 4 | Medium |
| Marketeer customers missing `marketeer_customer_details` | 2 | Medium |
| Repayments blocked (missing loan map) during promote | 3 | Low — investigate allocations |
