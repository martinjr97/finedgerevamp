# Loan rate type architecture

## Current model

- Each `loan_rate_types` row belongs to exactly one `loan_product_id`.
- `loan_rates` rows belong to a rate type (tenure bands, fees, term %, legacy multipliers).
- Loans snapshot `loan_rate_id` at booking; they do not store `loan_rate_type_id` directly.
- Companies, customer groups, and markets may reference `loan_rate_type_id` as a default for new loans.

## Reuse across products

**Implemented:** copy-to-product (`admin.loan-rate-types.copy-product`). Creates a new rate type on the target product with duplicated settings and rate rows. Codes must remain unique; existing types are never overwritten.

**Not implemented:** one rate type shared by many products. The current schema and assignments assume product-scoped rate types. Sharing one plan across products would risk:

- Product-specific limits, categories, and reporting rules diverging
- Changes to a shared plan affecting unrelated products without clear audit boundaries
- Company/group defaults pointing at a type that no longer matches a product context

A future design could introduce:

- `loan_product_rate_type` pivot (many-to-many), or
- A `rate_plan` entity independent of product, linked when products opt in

Until business requires a central rate table, **copying is the safer operational path**.
