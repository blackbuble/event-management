# 📚 Event Management System - Documentation

Welcome to the Event Management System documentation. All documentation files are organized by category for easy navigation.

---

## 📂 Documentation Structure

```
docs/
├── auth/           # Authentication & User Management
├── rbac/           # Role-Based Access Control (RBAC)
└── setup/          # Installation & Setup Guides
```

---

## 🔐 Authentication & User Management

**Location:** `docs/auth/`

- **[AUTH_MODULE.md](./auth/AUTH_MODULE.md)** - Complete authentication module documentation
  - Email/Password login
  - OTP login
  - API endpoints & examples
  - Service-Repository pattern implementation

---

## 🎭 Role-Based Access Control (RBAC)

**Location:** `docs/rbac/`

- **[RBAC_IMPLEMENTATION.md](./rbac/RBAC_IMPLEMENTATION.md)** - Complete RBAC implementation guide
  - Roles & permissions matrix
  - Policy implementation
  - Usage examples
  - Advanced features
  
- **[RBAC_SPATIE_SETUP.md](./rbac/RBAC_SPATIE_SETUP.md)** - Quick setup guide for Spatie Permission
  - Installation steps
  - Basic usage
  - Quick reference

---

## 🚀 Setup & Installation

**Location:** `docs/setup/`

- **[INSTALL_SPATIE.md](./setup/INSTALL_SPATIE.md)** - Spatie Permission installation guide
  - Detailed installation steps
  - Troubleshooting
  - Testing procedures
  
- **[SETUP_RBAC.md](./setup/SETUP_RBAC.md)** - RBAC setup instructions
  - Step-by-step setup
  - Verification checklist
  - Common issues

---

## 🎯 Quick Start

### 1. Install Dependencies
```bash
composer require spatie/laravel-permission
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Seed Roles
```bash
php artisan db:seed --class=RoleSeeder
```

### 4. Read Documentation
- Start with **[INSTALL_SPATIE.md](./setup/INSTALL_SPATIE.md)** for setup
- Then read **[RBAC_IMPLEMENTATION.md](./rbac/RBAC_IMPLEMENTATION.md)** for usage
- Check **[AUTH_MODULE.md](./auth/AUTH_MODULE.md)** for API endpoints

---

## 📋 Features

### Authentication
- ✅ Email/Password login
- ✅ OTP login
- ✅ Laravel Sanctum API tokens
- ✅ Email verification
- ✅ Password reset
- ✅ Soft deletes
- ✅ UUID for public IDs

### RBAC (Role-Based Access Control)
- ✅ 4 default roles: admin, organizer, staff, attendee
- ✅ Policy-based authorization
- ✅ Spatie Laravel Permission integration
- ✅ Role assignment on registration
- ✅ Permission checking in controllers & policies

---

## 🏗️ Architecture

### Service-Repository Pattern
```
Controller → Service → Repository → Model
```

### Key Components
- **Models:** User, Event, Booking, etc.
- **Services:** AuthService, EventService, NotificationService
- **Repositories:** UserRepository
- **Policies:** UserPolicy, EventPolicy, BookingPolicy
- **Controllers:** API Controllers (JSON responses)

---

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Spatie Permission Documentation](https://spatie.be/docs/laravel-permission/v6)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)

---

## 🆘 Need Help?

1. Check the relevant documentation in the folders above
2. Look for troubleshooting sections in setup guides
3. Review the permission matrix in RBAC_IMPLEMENTATION.md
4. Check API examples in AUTH_MODULE.md

---

**Last Updated:** 2025-12-31  
**Version:** 1.0  
**Laravel Version:** 12.x
