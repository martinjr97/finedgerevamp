<!-- @guards:admin @permissions:loans.create -->
# Apply for Loan on Behalf of Customer

This section is for users with `loans.create` permission.

## Where to Start

Menu path:

- `Loan Management` -> `Loan Application`

URL:

- [`/admin/loan-applications`](/admin/loan-applications)

## End-to-End Flow

1. Select loan product.
2. Search active customer for that product.
3. Enter loan details and calculate repayment.
4. Complete review step (and collateral step when product is collateral-based).
5. Submit application.

## Group Loan Note

Group loans use a separate wizard and lifecycle (members, group details, principal split, documents, review, approval, disbursement).

Use:

- [Group Loans: Customer to Takeout](group-loans-workflow.md)

## Data Typically Needed

- customer record already active and linked to selected loan product
- loan amount
- tenure (months)
- loan start date
- disbursement channel
- disbursement phone/target number

For collateral products, additionally capture:

- collateral type
- collateral value
- collateral description/inspection metadata (as needed)
- supporting images (optional)

## Outcome After Submission

- Loan is created.
- If loan approval is enabled (`approval.loans.create=true`), status becomes `pending_approval`.
- Loan appears in approvals queue and/or loan details pages for approvers.

## Related Pages

- Loans list: [`/admin/loans`](/admin/loans)
- Approvals queue: [`/admin/approvals`](/admin/approvals)
