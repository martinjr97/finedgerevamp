# Loan Management System API Documentation

## Overview

This API provides secure access to the Loan Management System for both Admin and Customer mobile applications. The API uses Laravel Sanctum for token-based authentication.

**Base URL**: `http://your-domain.com/api/v1`

**API Version**: v1

## Authentication

The API uses Bearer token authentication. Include the token in the `Authorization` header:

```
Authorization: Bearer {your-token-here}
```

### Token Management

- Tokens are issued upon successful login
- Tokens can be refreshed using the refresh endpoint
- Tokens can be revoked by logging out
- Tokens can be device-specific (optional `device_name` parameter)

## Rate Limiting

- **Authentication endpoints**: 5 requests per minute
- **Protected endpoints**: 60 requests per minute

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in current window

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
    "success": true,
    "message": "Optional success message",
    "data": {
        // Response data here
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        // Validation errors (if applicable)
    }
}
```

## Endpoints

### Health Check

**GET** `/api/v1/health`

Check API status.

**Response:**
```json
{
    "status": "ok",
    "timestamp": "2025-12-19T10:00:00Z",
    "version": "1.0.0"
}
```

---

## Admin Authentication

### Login

**POST** `/api/v1/admin/auth/login`

Authenticate an admin user.

**Request Body:**
```json
{
    "email": "admin@example.com",
    "password": "password123",
    "device_name": "iPhone 15 Pro" // Optional
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "admin": {
            "id": 1,
            "employee_number": "EMP001",
            "first_name": "John",
            "last_name": "Doe",
            "full_name": "John Doe",
            "email": "admin@example.com",
            "phone": "+260123456789",
            "is_active": true,
            "roles": [],
            "permissions": []
        },
        "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
        "token_type": "Bearer"
    }
}
```

### Get Current Admin

**GET** `/api/v1/admin/auth/me`

Get authenticated admin details.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "employee_number": "EMP001",
        "first_name": "John",
        "last_name": "Doe",
        "full_name": "John Doe",
        "email": "admin@example.com",
        "phone": "+260123456789",
        "is_active": true,
        "roles": [],
        "permissions": []
    }
}
```

### Logout

**POST** `/api/v1/admin/auth/logout`

Revoke the current access token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

### Refresh Token

**POST** `/api/v1/admin/auth/refresh`

Create a new token and revoke the current one.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "device_name": "iPhone 15 Pro" // Optional
}
```

**Response:**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
        "token_type": "Bearer"
    }
}
```

---

## Customer Authentication

### Login

**POST** `/api/v1/customer/auth/login`

Authenticate a customer user.

**Request Body:**
```json
{
    "phone": "0977123456",
    "pin": "1234",
    "device_name": "Samsung Galaxy S24" // Optional
}
```

**Note:** Phone number can be in any format (with/without country code, spaces, dashes). The system normalizes it automatically.

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "customer": {
            "id": 1,
            "first_name": "Jane",
            "last_name": "Smith",
            "full_name": "Jane Smith",
            "email": "jane@example.com",
            "phone": "0977123456",
            "status": "active",
            "kyc_status": "verified"
        },
        "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
        "token_type": "Bearer"
    }
}
```

### Get Current Customer

**GET** `/api/v1/customer/auth/me`

Get authenticated customer details.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "first_name": "Jane",
        "last_name": "Smith",
        "full_name": "Jane Smith",
        "email": "jane@example.com",
        "phone": "0977123456",
        "status": "active",
        "kyc_status": "verified"
    }
}
```

### Logout

**POST** `/api/v1/customer/auth/logout`

Revoke the current access token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

### Refresh Token

**POST** `/api/v1/customer/auth/refresh`

Create a new token and revoke the current one.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "device_name": "Samsung Galaxy S24" // Optional
}
```

**Response:**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
        "token_type": "Bearer"
    }
}
```

---

## Error Codes

| Status Code | Description |
|------------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthenticated |
| 403 | Forbidden (Account inactive, pending approval, etc.) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests (Rate Limit Exceeded) |
| 500 | Internal Server Error |

## Security Features

1. **Token-based Authentication**: Secure token generation using Laravel Sanctum
2. **Rate Limiting**: Prevents abuse and DDoS attacks
3. **CORS Configuration**: Configurable cross-origin resource sharing
4. **Account Status Checks**: Active and approval status validation
5. **Device Management**: Optional device-specific tokens
6. **Token Expiration**: Configurable token expiration (via `SANCTUM_TOKEN_EXPIRATION` env variable)

## Environment Configuration

Add these to your `.env` file:

```env
# Sanctum Configuration
SANCTUM_TOKEN_EXPIRATION=null  # null = no expiration, or set minutes (e.g., 60 for 1 hour)
SANCTUM_TOKEN_PREFIX=          # Optional token prefix for security scanning

# CORS Configuration
CORS_ALLOWED_ORIGINS=*          # Comma-separated list of allowed origins, or * for all
```

## Testing the API

You can test the API using tools like:
- **Postman**
- **cURL**
- **Insomnia**
- **HTTPie**

### Example cURL Request

```bash
# Admin Login
curl -X POST http://your-domain.com/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123",
    "device_name": "Test Device"
  }'

# Get Admin Details (after login)
curl -X GET http://your-domain.com/api/v1/admin/auth/me \
  -H "Authorization: Bearer {your-token-here}"
```

## Next Steps

Additional endpoints for loans, repayments, customers, etc. will be added in future updates. The API structure is designed to be easily extensible.

