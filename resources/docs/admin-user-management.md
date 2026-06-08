<!-- @guards:admin @permissions:admins.create -->
# Create Admin User

This section is for users with `admins.create` permission.

## Where to Create Admin Users

Menu path:

- `Users` -> `Create User`

URL:

- [`/admin/users/create`](/admin/users/create)

## Data Needed

Required or commonly used fields:

- company
- branch
- first name
- last name
- email
- phone (optional)
- employee number (optional)
- NRC (optional)
- role(s)
- relationship manager flag (optional)
- active status

## What Happens After Save

1. User record is created.
2. Roles are assigned.
3. If admin approval is enabled (`approval.admins.create=true`), status becomes `pending` and the record appears in [`/admin/approvals`](/admin/approvals).
4. If approval is not required, invitation email is sent immediately.

## Related Actions

- View all admins: [`/admin/users`](/admin/users)
- Approve pending admins: [`/admin/approvals`](/admin/approvals)
- Send reset link to an admin user (requires `admins.update`): available from Users module action controls
