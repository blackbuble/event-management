# 🛡️ Laporan Audit Keamanan & Pengujian Otomatis (Auth)

Laporan ini mendokumentasikan hasil pengujian keamanan, unit testing, dan QA otomatis pada modul autentikasi sistem Event Management. Fokus utama adalah pada ketahanan sistem terhadap serangan umum seperti brute force, manipulasi identitas, dan kebocoran data.

---

## 📊 Ringkasan Eksekutif
| Kategori | Status | Keterangan |
| :--- | :---: | :--- |
| **Pencegahan Brute Force** | ✅ LULUS | OTP hangus otomatis setelah 5 percobaan salah. |
| **Manipulasi Identitas** | ✅ LULUS | Server hanya percaya session, payload luar diabaikan. |
| **Rate Limiting** | ✅ LULUS | Throttling berbasis identitas (Email/Phone) aktif. |
| **Mass Assignment** | ✅ LULUS | Field sensitif dikunci dari input massal. |
| **Normalisasi Input** | ✅ LULUS | Mendukung format lokal (08) & internasional (+62). |

---

## 🧪 1. Pengujian Fitur (Black Box)
*Lokasi File: `tests/Feature/AuthSecurityTest.php`*

### **BB-01: OTP Brute Force Attack**
- **Skenario**: Melakukan input OTP salah sebanyak 5 kali berturut-turut.
- **Hasil**: Pada percobaan ke-6, sistem menolak input meskipun OTP yang dimasukkan benar, karena field OTP di database telah dihapus (`NULL`) secara otomatis demi keamanan.
- **Status**: ✅ **PASS**

### **BB-02: Identity Switching Prevention**
- **Skenario**: Penyerang mencoba memverifikasi OTP untuk nomor target dengan mengirimkan nomor target tersebut di body request, sementara session miliknya adalah nomor miliknya sendiri.
- **Hasil**: Sistem mengabaikan nomor di body request dan hanya memvalidasi terhadap identitas di session. Penyerang gagal membajak akun.
- **Status**: ✅ **PASS**

### **BB-03: Identity-based Rate Limiting**
- **Skenario**: Melakukan request OTP/Magic Link berkali-kali ke satu identitas yang sama.
- **Hasil**: Sistem memblokir request setelah percobaan ke-5 dalam satu menit, mencegah spamming biaya SMS/WhatsApp oleh penyerang.
- **Status**: ✅ **PASS**

---

## ⚙️ 2. Pengujian Unit (White Box)
*Lokasi File: `tests/Unit/AuthServiceSecurityTest.php`*

### **WB-01: Mass Assignment Audit**
- **Audit**: Mencoba memasukkan nilai `otp` atau `social_id` melalui fungsi `User::create()`.
- **Hasil**: Field tersebut tetap `NULL` karena tidak terdaftar dalam properti `$fillable` di Model User.
- **Status**: ✅ **PASS**

### **WB-02: Social Login Security**
- **Audit**: Mencoba menautkan akun sosial baru ke email yang sudah terdaftar dengan ID sosial berbeda.
- **Hasil**: Sistem melempar eksepsi dan menolak tautan untuk mencegah *account takeover*.
- **Status**: ✅ **PASS**

### **WB-03: Phone Normalization Logic**
- **Audit**: Menguji berbagai input: `0812...`, `+0812...`, `62812...`, dan spasi/strip.
- **Hasil**: Seluruh format dinormalisasi secara presisi menjadi format internasional standar E.164 (Contoh: `+628123456789`).
- **Status**: ✅ **PASS**

---

## 🛡️ 3. Perbaikan Vulnerability (Fix History)
Selama proses audit, beberapa kerentanan kritikal telah diidentifikasi dan diperbaiki:
1. **Broken Auth Flow**: Sebelumnya verifikasi OTP mengandalkan parameter URL yang mudah dimanipulasi. **Fix**: Sekarang menggunakan Session Server yang terenkripsi.
2. **Missing Rate Limit**: Sebelumnya hanya menggunakan throttling IP standar. **Fix**: Sekarang menggunakan throttling berbasis identitas untuk mencegah serangan terdistribusi (Distributed Attack).
3. **Data Exposure**: OTP sebelumnya tercatat di log server. **Fix**: Sekarang log hanya mencatat event tanpa mencatat kode asli.

---

## 🚀 Instruksi Menjalankan Tes
Untuk menjalankan ulang seluruh pengujian ini di masa mendatang:
1. Pastikan database `event_management_test` tersedia.
2. Konfigurasi `phpunit.xml` menggunakan koneksi MySQL.
3. Jalankan perintah:
```powershell
php artisan test --filter AuthSecurityTest
php artisan test --filter AuthServiceSecurityTest
```

---
**Dokumentasi ini dibuat pada 31 Desember 2025 sebagai bagian dari standar penyerahan kode yang aman.**
