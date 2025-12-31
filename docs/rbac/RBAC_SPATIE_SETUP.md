# Authentication & User Management (Spatie RBAC)

This module uses **Spatie Laravel Permission** for Role-Based Access Control.

## Setup

1. **Install Dependencies**:
   ```bash
   composer install
   ```

2. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

3. **Seed Roles**:
   ```bash
   php artisan db:seed --class=RoleSeeder
   ```

4. **Clear Cache** (if needed):
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

## Roles
- `admin` - Full access to all resources
- `organizer` - Can create and manage their own events
- `staff` - Can view all bookings and manage users
- `attendee` - Default role, can create and manage their own bookings

## Quick Usage
```php
// Assign Role
$user->assignRole('admin');

// Check Role
if ($user->hasRole('admin')) { ... }

// Check Multiple Roles
if ($user->hasAnyRole(['admin', 'organizer'])) { ... }

// Direct Permissions
$user->givePermissionTo('edit events');
```

## Documentation
For complete implementation details, permission matrix, and advanced usage, see:
**[RBAC_IMPLEMENTATION.md](./RBAC_IMPLEMENTATION.md)**
