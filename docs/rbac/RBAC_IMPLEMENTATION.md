# RBAC Implementation Summary

## ✅ Status: RBAC Spatie Sudah Diimplementasikan

Aplikasi ini sudah menggunakan **Spatie Laravel Permission** untuk Role-Based Access Control (RBAC).

---

## 📦 Package yang Digunakan

- **spatie/laravel-permission** v6.0 - Role & Permission Management
- **laravel/sanctum** v4.0 - API Authentication

---

## 🎭 Roles yang Tersedia

| Role | Deskripsi | Permissions |
|------|-----------|-------------|
| **admin** | Administrator dengan akses penuh | Semua akses (create, update, delete semua resource) |
| **organizer** | Event organizer | Buat & kelola event sendiri, lihat booking untuk event mereka |
| **staff** | Staff/karyawan | Lihat semua booking, kelola user |
| **attendee** | Peserta event (default) | Buat booking, lihat & kelola booking sendiri |

---

## 📁 File-File Penting

### 1. **Model User** (`app/Models/User.php`)
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

### 2. **Migration** (`database/migrations/2025_12_31_000004_create_permission_tables.php`)
- Tabel: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

### 3. **Seeder** (`database/seeders/RoleSeeder.php`)
- Membuat 4 roles: admin, organizer, staff, attendee

### 4. **Policies**
- **UserPolicy** - Mengatur akses ke User management
- **EventPolicy** - Mengatur akses ke Event management
- **BookingPolicy** - Mengatur akses ke Booking management

### 5. **Config** (`config/permission.php`)
- Konfigurasi Spatie Permission

---

## 🔐 Cara Penggunaan

### Assign Role saat Register
```php
// AuthService.php - line 35
$user->assignRole('attendee');
```

### Check Role di Policy
```php
// UserPolicy.php
public function create(User $user): bool
{
    return $user->hasRole('admin');
}

// EventPolicy.php
public function create(User $user): bool
{
    return $user->hasRole(['admin', 'organizer']);
}
```

### Check Role di Controller/Service
```php
if ($user->hasRole('admin')) {
    // Admin logic
}

if ($user->hasAnyRole(['admin', 'organizer'])) {
    // Admin or Organizer logic
}
```

### Load Roles di Response
```php
// AuthController.php - line 107
return response()->json([
    'data' => $request->user()->load('roles'),
]);
```

---

## 🚀 Setup Instructions

### 1. Install Dependencies
```bash
composer install
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Seed Roles
```bash
php artisan db:seed --class=RoleSeeder
```

### 4. Clear Cache (jika perlu)
```bash
php artisan config:clear
php artisan cache:clear
```

---

## 📋 Permission Matrix

| Action | Admin | Organizer | Staff | Attendee |
|--------|-------|-----------|-------|----------|
| **Users** |
| View All Users | ✅ | ❌ | ✅ | ❌ |
| View Own Profile | ✅ | ✅ | ✅ | ✅ |
| Create User | ✅ | ❌ | ❌ | ❌ |
| Update Any User | ✅ | ❌ | ❌ | ❌ |
| Update Own Profile | ✅ | ✅ | ✅ | ✅ |
| Delete User | ✅ | ❌ | ❌ | ❌ |
| **Events** |
| View All Events | ✅ | ✅ | ✅ | ✅ |
| Create Event | ✅ | ✅ | ❌ | ❌ |
| Update Own Event | ✅ | ✅ | ❌ | ❌ |
| Update Any Event | ✅ | ❌ | ❌ | ❌ |
| Delete Own Event | ✅ | ✅ | ❌ | ❌ |
| Delete Any Event | ✅ | ❌ | ❌ | ❌ |
| **Bookings** |
| View All Bookings | ✅ | ❌ | ✅ | ❌ |
| View Own Booking | ✅ | ✅ | ✅ | ✅ |
| View Event Bookings | ✅ | ✅ (own events) | ✅ | ❌ |
| Create Booking | ✅ | ✅ | ✅ | ✅ |
| Update Own Booking | ✅ | ✅ | ✅ | ✅ |
| Update Any Booking | ✅ | ❌ | ❌ | ❌ |
| Delete Own Booking | ✅ | ✅ | ✅ | ✅ |
| Delete Any Booking | ✅ | ❌ | ❌ | ❌ |

---

## 🔧 Advanced Usage

### Assign Multiple Roles
```php
$user->assignRole(['organizer', 'staff']);
```

### Remove Role
```php
$user->removeRole('attendee');
```

### Sync Roles
```php
$user->syncRoles(['admin']);
```

### Direct Permissions (Optional)
```php
// Create permission
Permission::create(['name' => 'edit events']);

// Assign to user
$user->givePermissionTo('edit events');

// Assign to role
$role = Role::findByName('organizer');
$role->givePermissionTo('edit events');

// Check permission
if ($user->can('edit events')) {
    // ...
}
```

### Middleware (untuk routes)
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:admin|organizer'])->group(function () {
    Route::apiResource('events', EventController::class);
});
```

---

## 📝 API Response Example

### Login Response
```json
{
    "message": "Login successful.",
    "data": {
        "user": {
            "id": 1,
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "name": "John Doe",
            "email": "john@example.com",
            "roles": [
                {
                    "id": 1,
                    "name": "attendee",
                    "guard_name": "web"
                }
            ]
        },
        "token": "1|abc123..."
    }
}
```

### /me Response
```json
{
    "data": {
        "id": 1,
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "John Doe",
        "email": "john@example.com",
        "roles": [
            {
                "id": 2,
                "name": "organizer",
                "guard_name": "web"
            }
        ]
    }
}
```

---

## 🐛 Troubleshooting

### Error: "Role does not exist"
```bash
# Pastikan seeder sudah dijalankan
php artisan db:seed --class=RoleSeeder

# Clear cache
php artisan cache:clear
```

### Error: "Table 'roles' doesn't exist"
```bash
# Jalankan migration
php artisan migrate
```

### Permission tidak bekerja
```bash
# Clear permission cache
php artisan permission:cache-reset

# Atau
php artisan cache:forget spatie.permission.cache
```

---

## 📚 Referensi

- [Spatie Laravel Permission Documentation](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [Laravel Authorization Documentation](https://laravel.com/docs/12.x/authorization)

---

**Last Updated:** 2025-12-31  
**Version:** 1.0  
**Status:** ✅ Production Ready
