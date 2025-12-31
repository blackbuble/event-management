# 🚀 Setup & Installation Documentation

This folder contains setup and installation guides for the Event Management System.

---

## 📄 Files

### [INSTALL_SPATIE.md](./INSTALL_SPATIE.md)
Comprehensive Spatie Permission installation guide:
- Detailed installation steps
- Multiple installation methods
- Troubleshooting common issues
- Testing procedures
- Verification checklist

### [SETUP_RBAC.md](./SETUP_RBAC.md)
RBAC setup instructions:
- Step-by-step setup guide
- Migration & seeding
- Verification steps
- Common issues & solutions

---

## 🚀 Quick Links

- **[Back to Main Documentation](../INDEX.md)**
- **[Authentication Documentation](../auth/)**
- **[RBAC Documentation](../rbac/)**

---

## 📋 Installation Checklist

### Prerequisites
- [ ] PHP 8.2 or higher
- [ ] Composer installed
- [ ] MySQL/PostgreSQL database
- [ ] Laravel 12 installed

### Installation Steps
- [ ] Install Spatie Permission package
- [ ] Run migrations
- [ ] Seed roles
- [ ] Clear cache
- [ ] Test RBAC functionality

---

## 🎯 Quick Start

### 1. Install Spatie Permission
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

### 4. Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
```

### 5. Verify Installation
```bash
php artisan tinker
>>> \Spatie\Permission\Models\Role::all();
```

For detailed instructions, see **[INSTALL_SPATIE.md](./INSTALL_SPATIE.md)**

---

## 🐛 Common Issues

### Package Not Found
**Solution:** Run `composer require spatie/laravel-permission`

### Migration Errors
**Solution:** Run `php artisan migrate:fresh` (⚠️ Warning: This will delete all data)

### Role Does Not Exist
**Solution:** Run `php artisan db:seed --class=RoleSeeder`

### Permission Cache Issues
**Solution:** Run `php artisan permission:cache-reset`

For complete troubleshooting, see **[INSTALL_SPATIE.md](./INSTALL_SPATIE.md)**

---

## 📚 Additional Resources

- [Spatie Permission Documentation](https://spatie.be/docs/laravel-permission/v6)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [Laravel Migration Documentation](https://laravel.com/docs/12.x/migrations)

---

**Last Updated:** 2025-12-31
