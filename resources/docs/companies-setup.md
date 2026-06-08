<!-- @guards:admin @any-permissions:companies.view,companies.create,companies.update -->
# Companies Setup (MOU and SME Prerequisite)

Use this module to register partner companies before onboarding customers tied to MOU or SME products.

## Why This Must Be Done First

For MOU and SME workflows, customer accounts are linked to a company.

That means:

1. Create the company first.
2. Configure company limits and schedule fields.
3. Then create customer accounts linked to that company.

Without this setup, MOU/SME customer onboarding and some loan/reporting workflows will be incomplete.

## Where to Find It

Menu path:

- `Companies` -> `Register Company`
- `Companies` -> `View Companies`

URLs:

- List: [`/admin/companies`](/admin/companies)
- Create: [`/admin/companies/create`](/admin/companies/create)
- Detail template: `/admin/companies/{company}`
- Payment Due Report selector: [`/admin/payment-due-report/select`](/admin/payment-due-report/select)
- Payment Due Report template: `/admin/companies/{company}/payment-due-report`

## Core Company Fields

### Identity and Agreement

- company name
- code (used to generate slug and identifiers)
- registration number
- TPIN
- date of incorporation
- MOU expiry date

### Ownership and Product Behaviour

- sector
- relationship manager
- interest rate type (`loan_rate_type_id`) for MOU-linked calculations

### Contact and Address

- contact email
- contact phone
- address, city, state, postal code, country

### Computation and Restriction Fields

These directly influence limits, cutoffs, schedules, and repayment behavior:

- `maximum_loan_tenure_months`
- `monthly_cut_off_day`
- `pay_day`
- `maximum_debit_ratio`
- `instalment_cross_over_percentage`
- `arrangement_fee_percentage`

## Important Operational Note

`pay_day` is required for company payment-due report workflows. If not set, report generation is blocked for that company.

## Recommended Sequence for MOU and SME

1. Create company record.
2. Set loan rate type and limit/schedule fields.
3. Verify status and approval state.
4. Create customer accounts linked to that company.
5. Continue with loan application or repayment workflows.
