# Setup Instructions - RBAC Spatie

## ⚠️ Action Required

Saya telah memperbarui `composer.json` untuk menambahkan package yang diperlukan:
- ✅ `spatie/laravel-permission` v6.0
- ✅ `laravel/sanctum` v4.0

## 🚀 Langkah Selanjutnya

Silakan jalankan command berikut di terminal Laragon Anda:

### 1. Install Dependencies
```bash
composer install
```

### 2. Publish Spatie Config (Optional - sudah ada manual)
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```
*Note: Config dan migration sudah dibuat manual, tapi Anda bisa publish ulang jika perlu*

### 3. Run Migrations
```bash
php artisan migrate
```

### 4. Seed Roles
```bash
php artisan db:seed --class=RoleSeeder
```

### 5. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
```

## ✅ Verifikasi

Setelah menjalankan command di atas, cek apakah RBAC sudah berfungsi:

### Test via Tinker
```bash
php artisan tinker
```

```php
// Check if roles exist
\Spatie\Permission\Models\Role::all();

// Should show: admin, organizer, staff, attendee

// Test assign role
$user = \App\Models\User::first();
$user->assignRole('admin');
$user->hasRole('admin'); // Should return true
```

### Test via API

**1. Register User**
```bash
POST /api/v1/register
{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

Response should include `roles` array with `attendee` role.

**2. Get User Info**
```bash
GET /api/v1/me
Authorization: Bearer {token}
```

Response should include user with roles.

## 📋 Checklist

- [ ] `composer install` berhasil
- [ ] `php artisan migrate` berhasil
- [ ] `php artisan db:seed --class=RoleSeeder` berhasil
- [ ] Roles tersedia di database (admin, organizer, staff, attendee)
- [ ] User baru otomatis mendapat role `attendee`
- [ ] Policy menggunakan `hasRole()` untuk authorization

## 📚 Dokumentasi

Lihat dokumentasi lengkap di:
- **[RBAC_IMPLEMENTATION.md](./RBAC_IMPLEMENTATION.md)** - Dokumentasi lengkap
- **[RBAC_SPATIE_SETUP.md](./RBAC_SPATIE_SETUP.md)** - Quick setup guide

## 🐛 Troubleshooting

### Error: "Class 'Spatie\Permission\PermissionServiceProvider' not found"
```bash
composer install
composer dump-autoload
```

### Error: "Table 'roles' doesn't exist"
```bash
php artisan migrate
```

### Error: "Role does not exist"
```bash
php artisan db:seed --class=RoleSeeder
php artisan cache:clear
```

---

**Status:** ⏳ Waiting for `composer install`  
**Next Step:** Run the commands above in your Laragon terminal
