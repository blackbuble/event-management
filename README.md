# Event Management System

Laravel 12 Event Management System with Authentication, RBAC, and API.

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Laravel 12

### Installation

1. **Clone & Install Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Install Spatie Permission**
   ```bash
   composer require spatie/laravel-permission
   php artisan db:seed --class=RoleSeeder
   ```

5. **Run Development Server**
   ```bash
   php artisan serve
   ```

---

## 📚 Documentation

All documentation is organized in the **`docs/`** folder:

### 📖 [Complete Documentation Index](./docs/INDEX.md)

### Quick Links

#### 🔐 Authentication
- [Authentication Module](./docs/auth/AUTH_MODULE.md) - Complete auth implementation guide

#### 🎭 RBAC (Role-Based Access Control)
- [RBAC Implementation](./docs/rbac/RBAC_IMPLEMENTATION.md) - Complete RBAC guide with permission matrix
- [RBAC Quick Setup](./docs/rbac/RBAC_SPATIE_SETUP.md) - Quick reference guide

#### 🚀 Setup & Installation
- [Install Spatie Permission](./docs/setup/INSTALL_SPATIE.md) - Detailed installation guide
- [RBAC Setup Guide](./docs/setup/SETUP_RBAC.md) - Step-by-step setup instructions

---

## ✨ Features

### Authentication & User Management
- ✅ Email/Password login
- ✅ OTP login
- ✅ Laravel Sanctum API tokens
- ✅ Email verification
- ✅ Password reset
- ✅ User roles & permissions

### RBAC (Role-Based Access Control)
- ✅ 4 default roles: **admin**, **organizer**, **staff**, **attendee**
- ✅ Policy-based authorization
- ✅ Spatie Laravel Permission integration
- ✅ Granular permission control

### Event Management
- ✅ Create, update, delete events
- ✅ Event booking system
- ✅ Booking reservations
- ✅ Event notifications

---

## 🏗️ Architecture

### Tech Stack
- **Backend:** Laravel 12
- **Authentication:** Laravel Sanctum
- **RBAC:** Spatie Laravel Permission
- **Database:** MySQL/PostgreSQL
- **API:** RESTful JSON API

### Design Patterns
- Service-Repository Pattern
- Policy-based Authorization
- UUID for public identifiers
- Soft Deletes
- Database Transactions

---

## 🎯 API Endpoints

### Authentication
```
POST   /api/v1/register          # Register new user
POST   /api/v1/login             # Login with email/password
POST   /api/v1/otp/generate      # Generate OTP
POST   /api/v1/otp/login         # Login with OTP
POST   /api/v1/logout            # Logout
GET    /api/v1/me                # Get current user
```

For complete API documentation, see [AUTH_MODULE.md](./docs/auth/AUTH_MODULE.md)

---

## 👥 Roles & Permissions

| Role | Description | Permissions |
|------|-------------|-------------|
| **admin** | System administrator | Full access to all resources |
| **organizer** | Event organizer | Create & manage own events |
| **staff** | Staff member | View bookings, manage users |
| **attendee** | Event attendee (default) | Create & manage own bookings |

For complete permission matrix, see [RBAC_IMPLEMENTATION.md](./docs/rbac/RBAC_IMPLEMENTATION.md)

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter AuthTest
```

---

## 📝 Development

### Code Style
```bash
# Format code
./vendor/bin/pint

# Check code style
./vendor/bin/pint --test
```

### Database
```bash
# Fresh migration with seed
php artisan migrate:fresh --seed

# Rollback
php artisan migrate:rollback
```

---

## 🐛 Troubleshooting

### Common Issues

**1. Spatie Permission not found**
```bash
composer require spatie/laravel-permission
php artisan cache:clear
```

**2. Migration errors**
```bash
php artisan migrate:fresh
php artisan db:seed --class=RoleSeeder
```

**3. Permission cache issues**
```bash
php artisan permission:cache-reset
php artisan cache:clear
```

For detailed troubleshooting, see [INSTALL_SPATIE.md](./docs/setup/INSTALL_SPATIE.md)

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🤝 Contributing

Contributions are welcome! Please read the documentation before submitting PRs.

---

## 📞 Support

For questions or issues:
1. Check the [documentation](./docs/INDEX.md)
2. Review troubleshooting guides
3. Open an issue on GitHub

---

**Built with ❤️ using Laravel 12**
