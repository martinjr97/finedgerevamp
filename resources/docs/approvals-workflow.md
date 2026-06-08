<!-- @guards:admin @permissions:approvals.view -->
# Approvals (Customers and Loans)

This section is for users who can access approval queues (`approvals.view`).

## Main Approval Queue

Menu path:

- `Approvals`

URL:

- [`/admin/approvals`](/admin/approvals)

This page groups pending:

- admin users
- companies
- customers
- loans
- transfers

## How to Approve Customers

Required action permission:

- `approvals.approve` (and `approvals.reject` for rejection)

Where to approve:

- [`/admin/approvals`](/admin/approvals) pending customer cards
- customer detail page (from [`/admin/customers`](/admin/customers)) when status is pending

## How to Approve Loans

Required action permission:

- `loans.approve` (and `loans.reject` for rejection)

Where to approve:

- [`/admin/approvals`](/admin/approvals) pending loans section
- loan detail page (from [`/admin/loans`](/admin/loans)) when status is `pending_approval`

## Operational Notes

- Add notes when approving/rejecting for audit clarity.
- If you can view queue items but cannot action them, your role likely has `approvals.view` without the action permissions.
