<!-- @guards:admin @permissions:customers.create -->
# Create Customers by Product Type

This section is for users with `customers.create` permission.

## Where to Create Customers

Menu path:

- `Customers` -> `Create Customer`

Start URL:

- [`/admin/customers/select-product-type`](/admin/customers/select-product-type)

## Prerequisite for MOU and SME Customers

Before creating MOU or SME customer accounts, create and configure the company first from:

- [`/admin/companies/create`](/admin/companies/create)

The customer account will be linked to that company and use company-level settings for restrictions and schedules.

## Workflow

1. Select product type.
2. Complete the product-specific form.
3. Submit customer details.
4. If customer approval is enabled (`approval.customers.create=true`), customer moves to pending approval queue.

## Common Core Data

Most product types require these customer details:

- first name and last name
- email
- national ID
- TPIN
- phone (digits only when provided)
- address fields
- loan product

## Product-Specific Required Data

### Government

- ministry
- date of employment
- gross salary
- net salary
- verified by (admin relationship manager)

### MOU

- company
- position
- department
- date of employment
- gross salary
- net salary
- verified by

### Character

- customer group
- next of kin name/phone/relationship
- employment flag
- net salary

### Collateral

- customer group
- next of kin name/phone/relationship
- employment flag
- net salary

Note: collateral details for a loan are captured during loan application, not during basic customer creation.

### Group Loans

- optional customer group (if empty, system falls back to a default group for that product)
- occupation type (`employed` or `business_owner`)
- employer or business name
- average income
- work/business location details (address line 1, city, country)

Note: group loan customers are onboarded from customer management, but group loan takeout is done in the dedicated Group Loans application wizard.

### Marketeer

- market
- next of kin full contact details
- monthly income

### SME

Two modes exist:

- `company` customer type: registered name, monthly net revenue, qualification percentage
- `representative` customer type: parent company customer, representative identity details

## Bulk Upload Option

From the create page you can also:

- download product template
- upload Excel batch for bulk customer onboarding

## Related Pages

- Customer list: [`/admin/customers`](/admin/customers)
- Pending approvals: [`/admin/approvals`](/admin/approvals)
- Group loans flow: [Group Loans: Customer to Takeout](group-loans-workflow.md)
