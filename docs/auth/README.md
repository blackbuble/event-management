# 🔐 Authentication & User Management Documentation

This folder contains documentation related to authentication and user management.

---

## 📄 Files

### [AUTH_MODULE.md](./AUTH_MODULE.md)
Complete authentication module documentation including:
- Email/Password login
- OTP login
- API endpoints with request/response examples
- Service-Repository pattern implementation
- Database schema
- Security features

---

## 🚀 Quick Links

- **[Back to Main Documentation](../INDEX.md)**
- **[RBAC Documentation](../rbac/)**
- **[Setup Guides](../setup/)**

---

## 📋 Topics Covered

### Authentication Methods
- ✅ Email/Password authentication
- ✅ OTP (One-Time Password) authentication
- ✅ Laravel Sanctum token-based auth

### Features
- ✅ User registration
- ✅ Login/Logout
- ✅ Email verification
- ✅ Password reset
- ✅ OTP generation & validation
- ✅ API token management

### Security
- ✅ Password hashing
- ✅ OTP expiration
- ✅ Token-based authentication
- ✅ UUID for public identifiers
- ✅ Soft deletes

---

## 🎯 API Endpoints

All authentication endpoints are prefixed with `/api/v1/`:

- `POST /register` - Register new user
- `POST /login` - Login with email/password
- `POST /otp/generate` - Generate OTP
- `POST /otp/login` - Login with OTP
- `POST /logout` - Logout user
- `GET /me` - Get current user info

For detailed API documentation with examples, see **[AUTH_MODULE.md](./AUTH_MODULE.md)**

---

**Last Updated:** 2025-12-31
