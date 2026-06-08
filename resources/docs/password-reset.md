<!-- @guards:admin -->
# Password and PIN Reset

This section explains where reset actions happen for admins and customers.

## Admin Forgot Password (Logged Out)

Use:

- [`/admin/password/forgot`](/admin/password/forgot)

Flow:

1. Enter account email.
2. Verify OTP/reset step as prompted.
3. Set a new password.
4. Sign in again at [`/admin/login`](/admin/login).

## Customer Forgot PIN (Logged Out)

Use:

- [`/customer/password/forgot`](/customer/password/forgot)

Flow includes OTP and security-question verification before PIN reset.

## Admin Change Password (Logged In)

Use:

- [`/admin/password/change`](/admin/password/change)

## Customer Change PIN (Logged In)

Use:

- [`/customer/pin/change`](/customer/pin/change)

## Reset Another User (Admin Action)

### Reset Admin User Password Link

- module: Users
- requires permission: `admins.update`
- action: send password reset link from admin user controls

### Reset Customer PIN

- module: Customers
- requires permission: `customers.reset-pin`
- action available from customer detail controls
