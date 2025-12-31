# 🎭 RBAC (Role-Based Access Control) Documentation

This folder contains documentation related to Role-Based Access Control using Spatie Laravel Permission.

---

## 📄 Files

### [RBAC_IMPLEMENTATION.md](./RBAC_IMPLEMENTATION.md)
Complete RBAC implementation guide including:
- Detailed permission matrix
- Policy implementation examples
- Advanced usage & features
- Troubleshooting guide
- API response examples

### [RBAC_SPATIE_SETUP.md](./RBAC_SPATIE_SETUP.md)
Quick setup guide for Spatie Permission:
- Installation steps
- Basic usage examples
- Quick reference

---

## 🚀 Quick Links

- **[Back to Main Documentation](../INDEX.md)**
- **[Authentication Documentation](../auth/)**
- **[Setup Guides](../setup/)**

---

## 🎭 Roles Overview

| Role | Description | Access Level |
|------|-------------|--------------|
| **admin** | System Administrator | Full access to all resources |
| **organizer** | Event Organizer | Create & manage own events |
| **staff** | Staff Member | View bookings, manage users |
| **attendee** | Event Attendee | Create & manage own bookings (default) |

---

## 📋 Key Features

### Role Management
- ✅ 4 pre-defined roles
- ✅ Automatic role assignment on registration
- ✅ Role-based route protection
- ✅ Policy-based authorization

### Permission System
- ✅ Granular permission control
- ✅ Role-permission assignment
- ✅ Direct user permissions
- ✅ Permission caching

### Implementation
- ✅ Spatie Laravel Permission v6
- ✅ Policy classes for each model
- ✅ Middleware support
- ✅ Blade directives

---

## 🔧 Quick Usage

### Check Role
```php
if ($user->hasRole('admin')) {
    // Admin logic
}
```

### Assign Role
```php
$user->assignRole('organizer');
```

### Check in Policy
```php
public function create(User $user): bool
{
    return $user->hasRole(['admin', 'organizer']);
}
```

For complete usage examples, see **[RBAC_IMPLEMENTATION.md](./RBAC_IMPLEMENTATION.md)**

---

## 📊 Permission Matrix

For the complete permission matrix showing what each role can do, see the **Permission Matrix** section in **[RBAC_IMPLEMENTATION.md](./RBAC_IMPLEMENTATION.md)**

---

**Last Updated:** 2025-12-31
