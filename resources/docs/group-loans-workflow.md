<!-- @guards:admin @any-permissions:customers.create,loans.create,loans.view,loans.approve,loans.disburse -->
# Group Loans: Customer Creation to Takeout

This guide covers the full admin flow for group loans, from onboarding members to disbursing approved member loans.

## Permissions by Stage

- Customer onboarding: `customers.create`
- Build and submit group loan application: `loans.create`
- View submitted group loan applications: `loans.view`
- Decisioning: `loans.approve` / `loans.reject` or `approvals.approve` / `approvals.reject`
- Disbursement: `loans.disburse`
- Optional relationship manager assignment in wizard: `can assign relationship manager to group`

## Stage 1: Create Group Loan Customers

Menu path:

- `Customers` -> `Create Customer`

Start URL:

- [`/admin/customers/select-product-type`](/admin/customers/select-product-type)

Workflow:

1. Select a loan product with category `group_loans`.
2. In **Group Context**, optionally select a customer group.
3. If no group is selected, the system assigns the customer to a default group (`GL-DEFAULT`) for that product.
4. Complete required group-loan onboarding fields:
   - occupation type (`employed` or `business_owner`)
   - employer or business name
   - average income
   - business/work address details
5. Submit customer creation.
6. Upload KYC from the customer KYC page.
7. If customer approval is enabled, the customer remains pending until approved.

## Stage 2: Group Loan Takeout Wizard

Menu path:

- `Loan Management` -> `Loan Application` -> `Group Loan Products`

Start URL:

- [`/admin/loan-applications`](/admin/loan-applications)

### Step 1: Select Members

Route pattern:

- `/admin/loan-applications/{loanProduct}/group-loans/members`

Rules:

- Select **3 to 10** members.
- Members must be active, approved customers in the same selected group and group-loan product.
- Every selected member must have a title.
- At least one selected member must be a **Leader** or **Coordinator**.

### Step 2: Loan Details

Route pattern:

- `/admin/loan-applications/{loanProduct}/group-loans/details`

Capture:

- group loan name
- repayment structure (weekly/monthly)
- start date and due date (or loan term value/unit to auto-calculate due date)
- processing fee percentage
- interest rate for full period
- arrears rate
- terms and conditions (optional)
- relationship manager (depending on permission)

### Step 3: Principal Allocation

Route pattern:

- `/admin/loan-applications/{loanProduct}/group-loans/principals`

Capture:

- principal amount for each selected member

System output:

- member-level processing fee, interest, total repayment, and disbursement amounts
- aggregate totals and installment preview

### Step 4: Supporting Documents

Route pattern:

- `/admin/loan-applications/{loanProduct}/group-loans/documents`

Notes:

- Document upload is optional.
- Allowed formats: JPG, PNG, PDF, DOC, DOCX.
- Continue to review when done.

### Step 5: Review and Submit

Route pattern:

- `/admin/loan-applications/{loanProduct}/group-loans/review`

Actions:

- review members, rates, totals, and repayment schedule
- download corporate copy PDF
- submit for approval

Outcome:

- application status becomes `pending_approval`

## Stage 3: Approval / Rejection

Applications list:

- [`/admin/loan-applications/group-loans`](/admin/loan-applications/group-loans)

Application detail:

- `/admin/loan-applications/group-loans/{id}`

Decision outcomes:

- **Approve** -> status moves to `awaiting_disbursement`; member loan records are created.
- **Reject (changes requested)** -> application becomes rejected with required modifications.
- **Reject (permanent)** -> application is closed from progression.

For change requests:

- Assigned relationship manager (or authorized assigner) can create a revision draft.
- Assigned relationship manager or original submitter can add modification notes to the decision trail.

## Stage 4: Disbursement

Disbursement page:

- `/admin/loan-applications/group-loans/{id}/disbursement`

Behavior:

- If automated mode is enabled, run **automated disbursement** for pending members.
- In manual mode, open each member loan and disburse using existing loan disbursement flow.
- Member statuses update individually; group application can be `partially_disbursed` until all are completed.

## Related Pages

- Customer onboarding manual: [Create Customers by Product Type](customer-creation-workflow.md)
- Group loan applications list: [`/admin/loan-applications/group-loans`](/admin/loan-applications/group-loans)
- Standard loan applications: [Apply for Loan on Behalf of Customer](admin-loan-application.md)
