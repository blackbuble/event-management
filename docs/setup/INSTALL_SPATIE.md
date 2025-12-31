# 🚨 Error: Spatie Package Not Installed

## Error Message
```
Target class [Spatie\Permission\PermissionRegistrar] does not exist.
```

---

## 📋 Root Cause
Package **`spatie/laravel-permission`** belum terinstall di `vendor/` folder.

Meskipun sudah ditambahkan di `composer.json`, package belum di-download karena `composer install` atau `composer update` belum dijalankan.

---

## ✅ Solution (Choose ONE)

### **Option 1: Install Spatie Package (RECOMMENDED)**

Buka **Laragon Terminal** dan jalankan:

```bash
composer require spatie/laravel-permission
```

Setelah berhasil, jalankan:

```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan cache:clear
```

---

### **Option 2: Use Temporary Seeder (Workaround)**

Jika Anda tidak bisa install package sekarang, gunakan temporary seeder:

```bash
php artisan db:seed --class=RoleSeederTemp
```

**⚠️ Warning:** Ini hanya membuat roles di database tanpa Spatie functionality. Anda TETAP HARUS install Spatie package nanti agar RBAC berfungsi penuh.

---

## 🔧 Detailed Installation Steps

### Step 1: Open Laragon Terminal
1. Klik kanan icon **Laragon** di system tray
2. Pilih **"Terminal"** atau **"Quick app" > "Terminal"**
3. Terminal akan terbuka di folder project Anda

### Step 2: Install Package
```bash
composer require spatie/laravel-permission
```

**Expected Output:**
```
Using version ^6.0 for spatie/laravel-permission
./composer.json has been updated
Running composer update spatie/laravel-permission
...
Package operations: 1 install, 0 updates, 0 removals
  - Installing spatie/laravel-permission (6.x.x)
...
```

### Step 3: Verify Installation
```bash
# Check if vendor folder exists
dir vendor\spatie\laravel-permission
```

Jika ada output folder/files, package sudah terinstall ✅

### Step 4: Run Migrations
```bash
php artisan migrate
```

### Step 5: Seed Roles
```bash
php artisan db:seed --class=RoleSeeder
```

**Expected Output:**
```
INFO  Seeding database.
✓ Roles seeded successfully
```

### Step 6: Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

---

## 🧪 Test RBAC Functionality

### Test 1: Check Roles in Database
```bash
php artisan tinker
```

```php
\Spatie\Permission\Models\Role::all();
// Should show: admin, organizer, staff, attendee
exit
```

### Test 2: Register New User via API
```bash
POST http://localhost/api/v1/register
Content-Type: application/json

{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Expected Response:**
```json
{
    "message": "User registered successfully.",
    "data": {
        "user": {
            "name": "Test User",
            "email": "test@example.com",
            "roles": [
                {
                    "name": "attendee",
                    "guard_name": "web"
                }
            ]
        },
        "token": "..."
    }
}
```

✅ User harus otomatis mendapat role **"attendee"**

---

## 🐛 Common Issues

### Issue 1: "composer: command not found"

**Solution A:** Use Laragon Terminal (not regular PowerShell)

**Solution B:** Add composer to PATH
```bash
# Check composer location
where composer

# Or use full path
C:\laragon\bin\composer\composer.phar require spatie/laravel-permission
```

---

### Issue 2: "Your requirements could not be resolved"

```bash
composer update --with-all-dependencies
```

Or:
```bash
composer install --ignore-platform-reqs
```

---

### Issue 3: Migration already exists

```bash
# Check existing migrations
php artisan migrate:status

# If permission tables already exist, skip migration
# Just seed the roles
php artisan db:seed --class=RoleSeeder
```

---

### Issue 4: "Class 'Spatie\Permission\Models\Role' not found"

This means package is not installed. Go back to **Step 2** and install the package.

---

## � Current Status

| Component | Status |
|-----------|--------|
| `composer.json` updated | ✅ Done |
| Package installed in vendor/ | ❌ **Pending** |
| Migrations created | ✅ Done |
| Seeders created | ✅ Done |
| Policies updated | ✅ Done |
| Documentation | ✅ Done |

**Next Action:** Install the package using `composer require spatie/laravel-permission`

---

## 📚 Files Created

1. **INSTALL_SPATIE.md** - This file
2. **RoleSeederTemp.php** - Temporary seeder (workaround)
3. **RBAC_IMPLEMENTATION.md** - Complete RBAC documentation
4. **SETUP_RBAC.md** - Setup instructions

---

## 🎯 Quick Commands Summary

```bash
# 1. Install package (REQUIRED)
composer require spatie/laravel-permission

# 2. Run migrations
php artisan migrate

# 3. Seed roles
php artisan db:seed --class=RoleSeeder

# 4. Clear cache
php artisan cache:clear

# 5. Test
php artisan tinker
>>> \Spatie\Permission\Models\Role::all();
```

---

**Status:** ⏳ Waiting for `composer require spatie/laravel-permission`  
**Priority:** 🔴 HIGH - Application won't work without this package
