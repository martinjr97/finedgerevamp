# Postman Collection for Loan Management System API

This directory contains Postman collection files for testing the Loan Management System API.

## Files

1. **Loan_Management_System_API.postman_collection.json** - Main API collection with all endpoints
2. **Loan_Management_System_API_Environment.postman_environment.json** - Environment variables for different environments

## Setup Instructions

### 1. Import Collection

1. Open Postman
2. Click **Import** button (top left)
3. Select **Loan_Management_System_API.postman_collection.json**
4. Click **Import**

### 2. Import Environment (Optional but Recommended)

1. Click **Import** button again
2. Select **Loan_Management_System_API_Environment.postman_environment.json**
3. Click **Import**
4. Select the imported environment from the environment dropdown (top right)

### 3. Configure Base URL

If you're using the environment file:
1. Click on the environment name (top right)
2. Click **Edit**
3. Update the `base_url` value:
   - **Local**: `http://127.0.0.1:8000`
   - **Staging**: `https://staging.yourdomain.com`
   - **Production**: `https://api.yourdomain.com`
4. Click **Save**

If you're not using the environment file, update the collection variable:
1. Right-click on the collection name
2. Select **Edit**
3. Go to **Variables** tab
4. Update `base_url` value
5. Click **Save**

## Usage

### Testing Admin Authentication

1. **Admin Login**
   - Open **Admin Authentication > Admin Login**
   - Update the request body with your admin credentials:
     ```json
     {
         "email": "your-admin@example.com",
         "password": "your-password",
         "device_name": "Postman Test Device"
     }
     ```
   - Click **Send**
   - The token will be automatically saved to the `admin_token` variable

2. **Get Current Admin**
   - Open **Admin Authentication > Get Current Admin**
   - The Authorization header is automatically set using `{{admin_token}}`
   - Click **Send**

3. **Admin Logout**
   - Open **Admin Authentication > Admin Logout**
   - Click **Send** to revoke the token

### Testing Customer Authentication

1. **Customer Login**
   - Open **Customer Authentication > Customer Login**
   - Update the request body with customer credentials:
     ```json
     {
         "phone": "0977123456",
         "pin": "1234",
         "device_name": "Postman Test Device"
     }
     ```
   - Click **Send**
   - The token will be automatically saved to the `customer_token` variable
   - **Note**: The login response now includes dashboard data (active loans, outstanding balance, available loan amount, etc.)

2. **Get Current Customer**
   - Open **Customer Authentication > Get Current Customer**
   - The Authorization header is automatically set using `{{customer_token}}`
   - Click **Send**

3. **Customer Logout**
   - Open **Customer Authentication > Customer Logout**
   - Click **Send** to revoke the token

### Testing Customer Endpoints

1. **Customer Dashboard**
   - Open **Customer Protected Routes > Customer Dashboard**
   - Click **Send** to get dashboard data including active loans and financial summary

2. **Get Profile**
   - Open **Customer Protected Routes > Get Profile**
   - Click **Send** to get customer profile information

3. **Change PIN**
   - Open **Customer Protected Routes > Change PIN**
   - Update the request body:
     ```json
     {
         "current_pin": "1234",
         "new_pin": "5678",
         "new_pin_confirmation": "5678"
     }
     ```
   - **Note**: If `must_change_pin` is true, `current_pin` is not required
   - Click **Send**

4. **Get FAQs**
   - Open **Customer Protected Routes > Get FAQs**
   - Click **Send** to get all FAQs available for authenticated customers
   - Returns FAQs with visibility 'authenticated' or 'both'

### Testing Customer PIN Reset

The PIN reset process follows these steps:

1. **Send OTP**
   - Open **Customer Password Reset > Send OTP**
   - Update request body with phone and national_id:
     ```json
     {
         "phone": "0977123456",
         "national_id": "123456789"
     }
     ```
   - Click **Send**
   - OTP will be sent to the customer's phone (check logs in development)

2. **Verify OTP**
   - Open **Customer Password Reset > Verify OTP**
   - Update request body with phone, national_id, and OTP:
     ```json
     {
         "phone": "0977123456",
         "national_id": "123456789",
         "otp": "123456"
     }
     ```
   - Click **Send**
   - Response includes security question that needs to be answered

3. **Verify Security Question**
   - Open **Customer Password Reset > Verify Security Question**
   - Update request body with phone, national_id, and security answer:
     ```json
     {
         "phone": "0977123456",
         "national_id": "123456789",
         "security_answer": "Your Answer"
     }
     ```
   - Click **Send**
   - Response includes reset token

4. **Reset PIN**
   - Open **Customer Password Reset > Reset PIN**
   - Update request body with token, phone, national_id, and new PIN:
     ```json
     {
         "token": "reset-token-from-previous-step",
         "phone": "0977123456",
         "national_id": "123456789",
         "pin": "1234",
         "pin_confirmation": "1234"
     }
     ```
   - Click **Send**

## Features

### Automatic Token Management

The collection includes scripts that automatically:
- Save tokens after successful login
- Save user IDs for reference
- Update tokens after refresh

### Pre-configured Headers

All authenticated requests automatically include:
- `Authorization: Bearer {{admin_token}}` or `Bearer {{customer_token}}`
- `Content-Type: application/json` for POST requests

### Environment Variables

The collection uses these variables:
- `base_url` - API base URL
- `admin_token` - Admin authentication token (auto-populated)
- `customer_token` - Customer authentication token (auto-populated)
- `admin_id` - Admin user ID (auto-populated)
- `customer_id` - Customer user ID (auto-populated)
- `company_id` - Company ID (manual - useful for filtering)
- `loan_product_id` - Loan Product ID (manual - useful for filtering)

## Public Configuration Endpoints

These endpoints don't require authentication and can be used to get app configuration:

1. **Get App Configuration**
   - Open **Public Configuration > Get App Configuration**
   - Click **Send** to get system name, tagline, logo URL, support information, and registration status
   - Returns:
     - `system_name` - Company/system name
     - `system_tagline` - System tagline/slogan
     - `logo_url` - URL to the company logo
     - `support_email` - Support email address
     - `support_phone` - Support phone number
     - `support_address` - Support address details
     - `customer_registration` - Registration status and allowed products/groups

2. **Check Registration Status**
   - Open **Public Configuration > Check Registration Status**
   - Click **Send** to check if customer registration is enabled
   - Returns:
     - `enabled` - Whether registration is enabled
     - `allowed_products` - Array of allowed product IDs
     - `allowed_groups` - Array of allowed group IDs

## Testing Tips

1. **Start with Health Check**: Always test the health endpoint first to ensure the API is accessible

2. **Get App Configuration**: Use the public config endpoint to get app branding and settings before authentication

2. **Login First**: Before testing protected endpoints, make sure you've logged in successfully

3. **Check Response**: Look for `"success": true` in responses to verify the request was successful

4. **Rate Limiting**: If you get 429 errors, you've hit the rate limit. Wait a minute before trying again.

5. **Token Expiration**: If you get 401 errors, your token may have expired. Log in again to get a new token.

## Troubleshooting

### 401 Unauthorized
- Make sure you've logged in and the token is saved
- Check that the Authorization header is correctly set
- Try logging in again to get a fresh token

### 403 Forbidden
- Check if your account is active
- For admins, verify approval status
- Ensure you're using the correct authentication (admin vs customer)
- **Permission Error**: Check if your admin role has the required Spatie permission (e.g., `customers.view`, `loans.approve`, etc.)

### 422 Validation Error
- Check the request body format
- Verify all required fields are present
- Check field types (e.g., PIN must be exactly 4 digits)

### 429 Too Many Requests
- You've exceeded the rate limit
- Wait 1 minute before trying again
- Authentication endpoints: 5 requests/minute
- Protected endpoints: 60 requests/minute

## Collection Structure

```
Loan Management System API
├── Health Check
│   └── Health Check
├── Public Configuration
│   ├── Get App Configuration
│   └── Check Registration Status
├── Admin Authentication
│   ├── Admin Login
│   ├── Get Current Admin
│   ├── Admin Logout
│   └── Refresh Admin Token
├── Customer Authentication
│   ├── Customer Login (includes dashboard data)
│   ├── Get Current Customer
│   ├── Customer Logout
│   └── Refresh Customer Token
├── Admin Protected Routes
│   ├── Admin Dashboard
│   ├── Customers
│   │   ├── List Customers
│   │   ├── Create Customer
│   │   ├── Get Customer
│   │   └── Update Customer
│   ├── Customer Registration Requests
│   │   ├── List Registration Requests
│   │   ├── Get Registration Request
│   │   └── Approve Registration Request
│   ├── Companies
│   │   ├── List Companies
│   │   ├── Create Company
│   │   ├── Get Company
│   │   └── Update Company
│   ├── Loan Products
│   │   ├── List Loan Products
│   │   └── Get Loan Product
│   ├── Support Tickets
│   │   ├── List Support Tickets
│   │   ├── Get Support Ticket
│   │   └── Update Support Ticket
│   ├── Loans
│   │   ├── List Loans
│   │   ├── Get Loan
│   │   ├── Approve Loan
│   │   └── Reject Loan
│   └── Repayments
│       ├── List Repayments
│       └── Get Repayment
├── Admin Password Reset
│   ├── Send OTP
│   ├── Verify OTP
│   └── Reset Password
├── Customer Protected Routes
│   ├── Customer Dashboard
│   ├── Get Profile
│   ├── Change PIN
│   └── Get FAQs
└── Customer Password Reset
    ├── Send OTP
    ├── Verify OTP
    ├── Verify Security Question
    └── Reset PIN
```

## New Admin Endpoints

### Customers
- **List Customers**: `GET /api/v1/admin/customers` - Requires `customers.view` permission
- **Find Customer by Phone/National ID**: `GET /api/v1/admin/customers/find?phone=...` or `?national_id=...` - Requires `customers.view` permission
- **Create Customer**: `POST /api/v1/admin/customers` - Requires `customers.create` permission
- **Get Customer**: `GET /api/v1/admin/customers/{id}` - Requires `customers.view` permission
- **Update Customer**: `PUT /api/v1/admin/customers/{id}` - Requires `customers.update` permission
- **Get Customer Loans**: `GET /api/v1/admin/customers/{id}/loans` - Requires `customers.view` permission
- **Get Customer Repayments**: `GET /api/v1/admin/customers/{id}/repayments` - Requires `customers.view` permission

### Customer Registration Requests
- **List Requests**: `GET /api/v1/admin/customer-requests` - Requires `customer-requests.view` permission
- **Get Request**: `GET /api/v1/admin/customer-requests/{id}` - Requires `customer-requests.view` permission
- **Approve Request**: `POST /api/v1/admin/customer-requests/{id}/approve` - Requires `customer-requests.approve` permission

### Companies
- **List Companies**: `GET /api/v1/admin/companies` - Requires `companies.view` permission
- **Create Company**: `POST /api/v1/admin/companies` - Requires `companies.create` permission
- **Get Company**: `GET /api/v1/admin/companies/{id}` - Requires `companies.view` permission
- **Update Company**: `PUT /api/v1/admin/companies/{id}` - Requires `companies.update` permission

### Loan Products
- **List Products**: `GET /api/v1/admin/loan-products` - Requires `loan-products.view` permission
- **Get Product**: `GET /api/v1/admin/loan-products/{id}` - Requires `loan-products.view` permission

### Support Tickets
- **List Tickets**: `GET /api/v1/admin/support-tickets` - No specific permission required
- **Get Ticket**: `GET /api/v1/admin/support-tickets/{id}` - No specific permission required
- **Update Ticket**: `PUT /api/v1/admin/support-tickets/{id}` - No specific permission required

### Loans
- **List Loans**: `GET /api/v1/admin/loans` - Requires `loans.view` permission
- **Get Loan**: `GET /api/v1/admin/loans/{id}` - Requires `loans.view` permission
- **Approve Loan**: `POST /api/v1/admin/loans/{id}/approve` - Requires `loans.approve` permission
- **Reject Loan**: `POST /api/v1/admin/loans/{id}/reject` - Requires `loans.reject` permission

### Repayments
- **List Repayments**: `GET /api/v1/admin/repayments` - Requires `repayments.view` permission
- **Get Repayment**: `GET /api/v1/admin/repayments/{id}` - Requires `repayments.view` permission

### Admin Password Reset (Public)
- **Send OTP**: `POST /api/v1/admin/password/send-otp` - No authentication required
- **Verify OTP**: `POST /api/v1/admin/password/verify-otp` - No authentication required
- **Reset Password**: `POST /api/v1/admin/password/reset` - No authentication required

## Next Steps

As more endpoints are added to the API, they will be included in this collection. You can also add your own requests to test additional functionality.

