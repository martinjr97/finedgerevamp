<!-- @guards:admin @any-permissions:settings.view,loan-products.view,loan-rate-types.view,channels.view,security-questions.view,sectors.view,ministries.view,provinces.view,branches.view -->
# Configurations and Setup

This section covers where core system configuration is done.

## Main Configuration Areas

Menu path:

- `Configurations`

Common screens:

- Product Types: [`/admin/loan-products`](/admin/loan-products)
- Collateral Types (inside product context):
  start at [`/admin/loan-products`](/admin/loan-products), then open a product and navigate to `/admin/loan-products/{loanProduct}/collateral-types`
- Interest Rate Types: [`/admin/loan-rate-types`](/admin/loan-rate-types)
- Sectors: [`/admin/sectors`](/admin/sectors)
- Ministries: [`/admin/ministries`](/admin/ministries)
- Provinces: [`/admin/provinces`](/admin/provinces)
- Branches: [`/admin/branches`](/admin/branches)
- Security Questions: [`/admin/security-questions`](/admin/security-questions)
- Payment Channels: [`/admin/channels`](/admin/channels)

## Settings Pages

These are separate setting screens:

- General settings: [`/admin/settings/general`](/admin/settings/general)
- Customer registration settings: [`/admin/settings/customer-registration`](/admin/settings/customer-registration)
- Repayment reminder settings: [`/admin/settings/repayment-reminders`](/admin/settings/repayment-reminders)
- Credit score settings: [`/admin/settings/credit-score`](/admin/settings/credit-score)

## Before Changing Configuration

1. Confirm your permission scope.
2. Validate impact on customer onboarding and loan flows.
3. Apply in controlled windows for high-impact changes.
4. Re-test loan creation, approvals, and repayment flows.
