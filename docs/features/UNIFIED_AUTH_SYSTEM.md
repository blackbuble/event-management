# Unified Authentication System Documentation

## 🚀 Overview
The Unified Authentication System is a frictionless identity management solution that allows users to log in or register using a single identifier (Email or WhatsApp). No passwords are required for the primary flow.

## 🛠 Architecture

### 1. Controllers
- **`AuthController`**: Orchestrates the unified flow.
    - `showLogin()`: Displays the entry page.
    - `authenticate()`: Detects identity type and triggers the appropriate service.
    - `otpLogin()`: Validates SMS/WhatsApp codes.
    - `authViaMagicLink()`: Validates email tokens.

### 2. Services
- **`AuthService`**: Contains core business logic.
    - `findOrCreateByIdentity()`: Handles automatic account creation.
    - `generateOtp()`: Creates a secure, time-limited 6-digit code.
    - `generateMagicLink()`: Creates a single-use login token.

### 3. Frontend (Inertia + React)
- **`Login.tsx`**: A smart input field that changes its UI (icons/labels) based on what the user types.
- **`VerifyOtp.tsx`**: A high-performance OTP input component with:
    - Paste support.
    - Auto-focus management.
    - Countdown timer (60s).
    - Auto-clear on error.

## 🔒 Security Features
- **Throttle**: 5 requests per minute limit on auth routes.
- **Fail-Safe OTP**: OTP is destroyed after 5 failed guesses.
- **Encrypted Session**: Session data is encrypted and cookies are HttpOnly.
- **Forced HTTPS**: Ensures data encryption in transit for production environments.

## 📦 Database Schema Changes
- **`users.phone`**: Unique, nullable string.
- **`users.email`**: Now nullable to support phone-only registrations.
- **`users.uuid`**: Primary public identifier.
- **`magic_link_tokens`**: Stores secure tokens for email login.

## 🧪 Integration (WhatsApp/Email)
To send actual messages, update `AuthService.php` in the following methods:
1.  **`generateOtp`**: Connect to your WhatsApp provider API.
2.  **`generateMagicLink`**: Trigger a Laravel `Mailable`.

---
*Created by Antigravity*
