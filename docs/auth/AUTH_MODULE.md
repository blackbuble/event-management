# Authentication & User Management Module

## Overview
This module implements a robust authentication system using **Laravel 12** best practices, following a **Service-Repository Pattern** and **Policy-based Authorization**. It includes Email/Password login, OTP login, and Role-Based Access Control (RBAC).

## Architecture
- **Service Layer**: `AuthService` handles all business logic (hashing, token creation, OTP generation).
- **Repository Pattern**: `UserRepository` abstracts database queries.
- **API-First**: `AuthController` manages HTTP requests/responses and returns JSON.
- **Security**: Laravel Sanctum for API Tokens, UUIDs for public IDs.

## Setup Instructions

1.  **Install Sanctum** (if not already installed):
    ```bash
    composer require laravel/sanctum
    php artisan install:api
    ```
    *Note: If `php artisan install:api` fails or you are on an older Laravel version, ensure `bootstrap/app.php` registers `routes/api.php` (already configured by this module).*

2.  **Run Migrations**:
    ```bash
    php artisan migrate
    ```

## Database Schema Changes
- **Users Table**: Added `uuid`, `otp`, `otp_expires_at`, `deleted_at`.
- **Roles Table**: Created `roles` (id, uuid, name, slug).
- **Role_User Table**: Pivot table for User-Role Many-to-Many relationship.

## API Endpoints & Example JSON

### 1. Register
**Endpoint:** `POST /api/v1/register`

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+1234567890"
}
```

**Response (201 Created):**
```json
{
    "message": "User registered successfully.",
    "data": {
        "user": {
            "id": 1,
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "name": "John Doe",
            "email": "john@example.com",
            "roles": [
                {
                    "id": 2,
                    "name": "Attendee",
                    "slug": "attendee"
                }
            ]
        },
        "token": "1|laravel_sanctum_token_string..."
    }
}
```

### 2. Login (Email + Password)
**Endpoint:** `POST /api/v1/login`

**Request:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200 OK):**
```json
{
    "message": "Login successful.",
    "data": {
        "user": { ... },
        "token": "2|laravel_sanctum_token_string..."
    }
}
```

### 3. Generate OTP
**Endpoint:** `POST /api/v1/otp/generate`

**Request:**
```json
{
    "email": "john@example.com"
}
```

**Response (200 OK):**
```json
{
    "message": "OTP sent to your email."
}
```

### 4. Login with OTP
**Endpoint:** `POST /api/v1/otp/login`

**Request:**
```json
{
    "email": "john@example.com",
    "otp": "123456"
}
```

**Response (200 OK):**
```json
{
    "message": "Login successful.",
    "data": { ... }
}
```

### 5. Get Current User
**Endpoint:** `GET /api/v1/me`
**Headers:** `Authorization: Bearer <token>`

**Response (200 OK):**
```json
{
    "data": {
        "id": 1,
        "uuid": "...",
        "name": "John Doe",
        "roles": [...]
    }
}
```

## Files Created/Modified
- `app/Models/User.php`: Added UUID, Roles, SoftDeletes traits.
- `app/Models/Role.php`: Role model.
- `app/Repositories/UserRepository.php`: Database abstraction.
- `app/Services/AuthService.php`: Auth logic (Login, Register, OTP).
- `app/Http/Controllers/Api/AuthController.php`: API Controller.
- `app/Policies/UserPolicy.php`: Authorization rules.
- `routes/api.php`: API Routes definition.
- `database/migrations/`: New migrations for roles and user modifications.
