# 🛡️ Laporan Audit Keamanan & Pengujian Otomatis (Create Event)

Laporan ini mendokumentasikan hasil QA fitur **Create Event** (`GET /dashboard/events/create`, `POST /dashboard/events`). Audit mencakup pengujian fungsional black box, pengujian unit white box, evaluasi arsitektur, dan sejarah perbaikan vulnerability yang ditemukan selama pengujian.

**Tanggal:** 13 September 2026
**Status:** ✅ LULUS — semua temuan audit pertama telah diremediasi (round 2)

---

## 📊 Ringkasan Eksekutif

| Kategori | Status | Keterangan |
| :--- | :---: | :--- |
| **Autorisasi (RBAC)** | ✅ LULUS | Dual enforcement: `Gate` di GET + Policy di Form Request POST. Attendee 403 di keduanya. |
| **Mass Assignment** | ✅ LULUS | Hanya `$request->validated()` yang masuk Service; `$fillable` whitelist aktif. |
| **Integritas Data (Slug Race)** | ✅ LULUS | `lockForUpdate` dalam transaksi + retry `UniqueConstraintViolationException`. |
| **Validasi Input** | ✅ LULUS | 18 aturan sesuai skema tabel `events`; normalisasi string kosong → `null`. |
| **Upload Gambar** | ✅ LULUS | MIME image + max 2MB; disimpan di disk `public/events/`. |
| **CSRF** | ✅ LULUS | Route web group; Inertia auto-attach `X-XSRF-TOKEN`. |
| **Double-Submit Protection** | ✅ LULUS *(remediasi R2)* | `Cache::lock` per-organizer + idempotensi natural-key (window 60s); duplikat return row existing tanpa menyimpan banner orphan. |
| **Rate Limiting pada Store** | ✅ LULUS *(remediasi R2)* | `throttle:10,1` pada `POST /dashboard/events`; request ke-11 → 429 (tertest). |
| **Meeting Link Ditangguhkan (R3)** | ✅ LULUS | Link opsional saat create (opsi "Masukkan Nanti"); diisi belakangan via `PATCH .../meeting-link` (policy `update`). |
| **Pengiriman Link ke Peserta (R3)** | ✅ LULUS | Email ter-queue + WhatsApp ter-log ke peserta **confirmed** saja, dedupe per user; guard link terisi; `throttle:5,1`; 429 tertest. |
| **Guard Tipe & Status Event (R4)** | ✅ LULUS | Link ditolak untuk event offline (validasi); kirim diblok untuk event cancelled/berakhir; PATCH + send keduanya ber-throttle. |
| **Send Concurrency (R4)** | ✅ LULUS | `Cache::lock` per event mencegah double-click concurrent → email ganda; timeout → flash error. |
| **Akurasi Waktu Email (R4)** | ✅ LULUS | Waktu event dirender di `app.timezone` dengan singkatan aktual (bukan hardcode "WIB"). |
| **Payload List (R4)** | ✅ LULUS | My Events ter-paginasi (10/halaman) + meta pagination. |
| **Landing Page Publik (R5)** | ✅ LULUS | `GET /events/{slug}` 200 untuk published; draft/cancelled → owner/admin saja (404 lain); payload lengkap (organizer, koordinat, tiket). |
| **Lifecycle Actions (R5)** | ✅ LULUS | View/Edit/Publish/Cancel/Delete dengan policy per-endpoint; guard transisi status & booking aktif; slug tak dapat dibajak. |
| **Integration Contract (R5)** | ✅ LULUS | Kontrak props `Events/Show` ↔ `getPublicEventData` diverifikasi test + smoke live (HTTP 200, organizer terkirim). |
| **Zona Waktu Event (R6)** | ✅ LULUS | `APP_TIMEZONE=Asia/Jakarta` + prop `timezone` + format eksplisit; payload ISO ber-offset `+07:00` (RE-13). |
| **Rating Penyelenggara (R6)** | ✅ LULUS | Tabel `event_reviews` + eligibility ketat + endpoint review + agregat avg/count di landing; UI form & hasil. |
| **Peserta Terdaftar (R6)** | ✅ LULUS | `attendees.count` (confirmed, distinct) + avatar stack; publik hanya inisial/avatar, bukan nama lengkap. |
| **Sticky Navbar (R6)** | ✅ LULUS | Nama event + tanggal muncul saat scroll, aksi kembali/share/edit tetap. |
| **Middleware `verified` Inert** | ✅ DIHAPUS *(remediasi R2)* | Dihapus dari route dashboard & event (dead config; `User` tidak implement `MustVerifyEmail`). |
| **Dead Code Controller** | ✅ DIHAPUS *(remediasi R2)* | `app/Http/Controllers/EventController.php` (Blade-style) dihapus; zero references terverifikasi. |

**Bukti eksekusi:** `php artisan test` → **101 passed (605 assertions)** — modul event: Feature 21 (create) + 16 (meeting link) + 18 (landing & lifecycle) + 13 (reviews) + Unit 12 (service) + 3 (notification), sisanya suite existing. `./vendor/bin/pint --test` → pass (file modul). `npx tsc --noEmit` → hanya error TS6305 pre-existing. Smoke live: `GET /events/xyz-music-festival` → HTTP 200 dengan payload `timezone:"Asia/Jakarta"`, `attendees:{count:0}`, `organizer.rating:{average:null,count:0}`, `can_review:false`.

---

## 🧪 1. Pengujian Fitur (Black Box)
*Lokasi File: `tests/Feature/EventCreationTest.php` (21 test) + `tests/Feature/MeetingLinkTest.php` (16 test)*

### **EV-01: Guest Protection**
- **Skenario**: Akses halaman create & POST store tanpa login.
- **Hasil**: Redirect ke `/login`, tabel `events` tetap kosong.
- **Status**: ✅ **PASS**

### **EV-02: Role Attendee Ditolak**
- **Skenario**: Attendee GET halaman create + POST data valid.
- **Hasil**: 403 Forbidden di keduanya; `EventPolicy::create` (admin/organizer only) terpicu via `Gate::authorize` (GET) dan `StoreEventRequest::authorize()` (POST).
- **Status**: ✅ **PASS**

### **EV-03: Profile Incomplete Redirect**
- **Skenario**: Organizer tanpa nomor telepon mengakses halaman create.
- **Hasil**: Redirect ke `onboarding.show` oleh middleware `profile.complete` (konsisten dengan guard dashboard).
- **Status**: ✅ **PASS**

### **EV-04: Render Halaman Inertia**
- **Skenario**: Organizer GET halaman create.
- **Hasil**: Komponen `Events/Create` ter-render (assertion `assertInertia`).
- **Status**: ✅ **PASS**

### **EV-05: Buat Event Draft (Offline)**
- **Skenario**: POST data offline lengkap, status `draft`.
- **Hasil**: Row tersimpan dengan `user_id` owner, slug otomatis, redirect `/dashboard` + flash message.
- **Status**: ✅ **PASS**

### **EV-06: Buat Event Online — Default Venue**
- **Skenario**: POST event `online` tanpa venue, dengan meeting link, langsung `published`.
- **Hasil**: `venue_name` = `Online Event`, `venue_address` = meeting link (default dari Service), data tersimpan.
- **Status**: ✅ **PASS**

### **EV-07: Banner Upload Storage**
- **Skenario**: POST dengan `UploadedFile::fake()->image()`.
- **Hasil**: File tersimpan di disk `public` prefix `events/` (assertion `Storage::assertExists`).
- **Status**: ✅ **PASS**

### **EV-08: Matriks Validasi (8 skenario)**
- Field wajib kosong → error `title, description, type, start_date, end_date, status` ✅
- `end_date` < `start_date` → error ✅
- `start_date` di masa lalu (`after:now`) → error ✅
- Event online tanpa `meeting_link` (`required_if`) → error ✅
- Event offline tanpa venue (`required_unless`) → error ✅
- `capacity` = 0 dan non-numerik (`min:1`, `integer`) → error ✅
- `latitude` = 999 (`between:-90,90`) → error ✅
- `status` = `cancelled` saat create (di luar whitelist `draft,published`) → error ✅
- **Status**: ✅ **PASS (8/8)**

### **EV-09: Slug Duplikat**
- **Skenario**: Dua event dengan judul identik (namun jadwal berbeda) dibuat berurutan.
- **Hasil**: Slug unik (`indie-music-festival-2026` dan `-2026-2`), tidak ada constraint violation. Jadwal sengaja dibedakan agar tidak tertangkap idempotensi EV-10.
- **Status**: ✅ **PASS**

### **EV-10: Double-Submit Idempotent**
- **Skenario**: POST payload identik dua kali berturut-turut oleh organizer yang sama.
- **Hasil**: Hanya 1 row di tabel `events`; kedua response redirect sukses ke `/dashboard`. Banner tidak disimpan dua kali (image store di-skip untuk duplikat).
- **Status**: ✅ **PASS**

### **EV-11: Rate Limiting Store**
- **Skenario**: 11 POST ke `events.store` dalam satu menit.
- **Hasil**: Request ke-11 → HTTP 429 (throttle `10,1`).
- **Status**: ✅ **PASS**

### **ML-01: Meeting Link Ditangguhkan** *(Round 3)*
- **Skenario**: Create event online & hybrid tanpa meeting link.
- **Hasil**: Event tersimpan, `meeting_link` NULL; URL invalid tetap ditolak.
- **Status**: ✅ **PASS**

### **ML-02: Set Link oleh Non-Owner / Attendee** *(Round 3)*
- **Skenario**: Organizer lain & attendee mencoba `PATCH .../meeting-link`.
- **Hasil**: 403 Forbidden (policy `update`); data event tidak berubah.
- **Status**: ✅ **PASS**

### **ML-03: Kirim Link — Hanya Peserta Confirmed** *(Round 3)*
- **Skenario**: Event dengan peserta confirmed (email+phone), confirmed (phone-only), pending, dan peserta event lain. Lalu user dengan 2 booking confirmed.
- **Hasil**: Hanya peserta confirmed event tsb yang ter-queue email; dedupe per user (1 user 2 booking → 1 email); booking pending & peserta event lain dikecualikan.
- **Status**: ✅ **PASS**

### **ML-04: Guard & Throttle Kirim** *(Round 3)*
- **Skenario**: Kirim saat link belum diisi; lalu 6x kirim beruntun.
- **Hasil**: Link belum diisi → redirect + flash error, tidak ada email ter-queue; request ke-6 → HTTP 429.
- **Status**: ✅ **PASS**

### **ML-05: Link Ditolak untuk Event Offline** *(Round 4)*
- **Skenario**: `PATCH .../meeting-link` pada event `offline`.
- **Hasil**: Error validasi `meeting_link`; kolom tetap NULL di DB.
- **Status**: ✅ **PASS**

### **ML-06/07: Kirim Diblok untuk Event Cancelled / Berakhir** *(Round 4)*
- **Skenario**: Send pada event `cancelled` dan event yang `end_date`-nya lewat, keduanya dengan peserta confirmed.
- **Hasil**: Redirect + flash error; `Mail::assertNothingQueued` — nol broadcast.
- **Status**: ✅ **PASS (2/2)**

### **ML-08: Paginasi My Events** *(Round 4)*
- **Skenario**: 12 event milik satu organizer; akses halaman 1 dan 2.
- **Hasil**: Halaman 1 = 10 row + meta (`total: 12, last_page: 2`); halaman 2 = 2 row + `current_page: 2`.
- **Status**: ✅ **PASS**

### **EM-01: Landing Page & Kontrak Payload** *(Round 5)*
- **Skenario**: Event published diakses guest; draft diakses guest & owner; cancelled diakses user lain; slug tak dikenal; satu tiket `quantity 100, sold 20`.
- **Hasil**: Published → 200; draft guest → 404, owner → 200 (`is_owner: true`); cancelled non-owner → 404; unknown → 404; payload berisi `slug`, `latitude`, `longitude`, `organizer{id,name}`, dan `tickets[0].remaining = 80`.
- **Status**: ✅ **PASS (EM-01a–e)**

### **EM-02: Otorisasi Edit** *(Round 5)*
- **Skenario**: Owner, organizer lain, dan attendee membuka halaman edit.
- **Hasil**: Owner 200 + props event; non-owner & attendee 403.
- **Status**: ✅ **PASS**

### **EM-03: Update Event & Immutabilitas Slug** *(Round 5)*
- **Skenario**: Update valid (termasuk mengirim `slug` palsu di payload); banner diganti file baru; update tanpa file; `end_date` sebelum `start_date`.
- **Hasil**: Data diperbarui; **slug tidak berubah** meski dikirim di payload; banner lama terhapus + file baru tersimpan; tanpa upload banner lama tetap; validasi tanggal gagal.
- **Status**: ✅ **PASS (4/4)**

### **EM-04: Transisi Status Publish/Cancel** *(Round 5)*
- **Skenario**: Publish draft; publish event yang sudah published; cancel event published; cancel event yang sudah cancelled; non-owner mencoba keduanya.
- **Hasil**: Draft→published sukses; publish ganda & cancel ganda → flash error tanpa perubahan; non-owner → 403.
- **Status**: ✅ **PASS (5/5)**

### **EM-05: Guard Hapus Event** *(Round 5)*
- **Skenario**: Hapus event tanpa booking; hapus event dengan booking confirmed.
- **Hasil**: Tanpa booking → soft-deleted (`deleted_at` terisi); dengan booking aktif → flash error, event tetap ada.
- **Status**: ✅ **PASS (2/2)**

### **EM-06: Proteksi Guest** *(Round 5)*
- **Skenario**: Guest mengakses edit/update/publish/cancel/delete.
- **Hasil**: Semua redirect ke `/login`.
- **Status**: ✅ **PASS**

### **RE-01…RE-08: Eligibility & Validasi Review** *(Round 6)*
- **Skenario**: Guest; peserta confirmed pada event selesai; pengguna tanpa booking; owner event; event belum selesai; event cancelled; review kedua; rating 0/6/"five".
- **Hasil**: Hanya peserta confirmed event selesai yang dapat menilai; sisanya flash error tanpa row; rating di luar 1–5 ditolak validasi; duplikat ditolak (tetap 1 row).
- **Status**: ✅ **PASS (8/8)**

### **RE-09…RE-12: Kontrak Landing Reputasi & Peserta** *(Round 6)*
- **Skenario**: `can_review`/`my_review` sebelum & sesudah submit; agregat rating 2 review (5 & 3) dari event berbeda; past events vs current & future; 2 booking confirmed + 1 pending.
- **Hasil**: `can_review` true→false setelah submit, `my_review` terisi; agregat `average 4, count 2`; hanya event selesai & bukan current yang tampil; `attendees.count 2`, inisial `BS`/`SD`, nama lengkap tidak ada di payload.
- **Status**: ✅ **PASS (4/4)**

### **RE-13: Offset Zona Waktu pada Payload** *(Round 6)*
- **Skenario**: Event dibuat dengan `Asia/Jakarta`; periksa `event.start_date` pada payload landing.
- **Hasil**: ISO memuat offset `+07:00` → waktu tampil sesuai jam yang diisi organizer.
- **Status**: ✅ **PASS**

---

## ⚙️ 2. Pengujian Unit (White Box)
*Lokasi File: `tests/Unit/EventServiceTest.php` (8 test) + `tests/Unit/NotificationServiceTest.php` (3 test) — repository/notification di-mock, tanpa DB*

### **WB-01: Delegasi Repository + Owner**
- **Audit**: Service memanggil `EventRepository::create` dengan `user_id` owner ter-set dan tanpa key `image` saat tidak ada upload.
- **Status**: ✅ **PASS**

### **WB-02: Short-Circuit Duplikat Tanpa Image Store**
- **Audit**: `findRecentDuplicate` mengembalikan event existing → Service return event tersebut, `create` tidak pernah dipanggil, dan tidak ada file tersimpan di disk `public/events/`.
- **Status**: ✅ **PASS**

### **WB-03: Default Venue Online**
- **Audit**: Input online tanpa venue → payload ke repository berisi default `Online Event` / meeting link.
- **Status**: ✅ **PASS**

### **WB-04: Venue Offline Tidak Dimutasi**
- **Audit**: Input offline dengan venue → payload diteruskan apa adanya (tidak ada magic overwrite).
- **Status**: ✅ **PASS**

### **WB-05: Kontrak Path Image**
- **Audit**: Upload → payload ke repository berisi path `events/*.jpg` (storage path, bukan objek File).
- **Status**: ✅ **PASS**

### **WB-06: Delegasi Update Meeting Link** *(Round 3)*
- **Audit**: Service mendelegasikan persistensi link ke `EventRepository::updateMeetingLink` tanpa logika tambahan.
- **Status**: ✅ **PASS**

### **WB-07: Notifikasi Per-User + Hitung Channel** *(Round 3)*
- **Audit**: 3 peserta (email+phone, phone-only, email-only) → `NotificationService::sendMeetingLink` dipanggil 3x; counts `emailed: 2, whatsapped: 2`.
- **Status**: ✅ **PASS**

### **WB-08: Kontrak Channel NotificationService** *(Round 3/4)*
- **Audit**: User ber-email → mailable ter-queue (`hasTo` benar) + return `emailed: true`; user ber-phone → log `WA_MEETING_LINK_SENDING` + return `whatsapped: true`; user tanpa channel → no-op + return false keduanya. Hitungan service bersumber dari return ini (single source of truth).
- **Status**: ✅ **PASS (3/3)**

---

## 🛡️ 3. Perbaikan Vulnerability (Fix History)

Ditemukan selama siklus pengujian, semua sudah diperbaiki sebelum merge:

### Round 1 (implementasi awal)

1. **Missing Authorization pada GET Create Page (HIGH)** — Halaman `GET /dashboard/events/create` awalnya hanya me-render form tanpa cek policy, sehingga attendee bisa membuka form (POST sudah terlindungi). Terdeteksi oleh `EV-02` yang menerima 200 alih-alih 403. **Fix**: `Gate::authorize('create', Event::class)` di `Web\EventController::create()`.
2. **Validasi Venue Terlalu Ketat untuk Event Online (MEDIUM)** — Rule `string` tanpa `nullable` menolak `venue_name: null` pada event online meski `required_unless` sudah meloloskan. **Fix**: prepend `nullable` pada rule `venue_name`/`venue_address`.
3. **Test Fixture Collision (LOW)** — Phone fixture statis menyengsarakan `users_phone_unique` antar test. **Fix**: `fake()->unique()->numerify()`.

### Round 2 (remediasi feedback QA)

4. **Double-Submit Tanpa Guard Server-Side (MEDIUM)** — Perlindungan hanya client-side (button disabled). **Fix**: `Cache::lock('event-create:{userId}')` (TTL 15s, wait 5s) + idempotensi natural-key via `EventRepository::findRecentDuplicate` (window 60s, key: user + title + type + jadwal + venue). `LockTimeoutException` ditangani controller → flash error ter-lokalize. Image store dipindah setelah dedupe untuk mencegah orphan file.
5. **Rate Limiting Store Absent (LOW)** — **Fix**: `throttle:10,1` pada route `events.store`; dibuktikan EV-11 (429 pada request ke-11).
6. **Middleware `verified` Inert (PRE-EXISTING)** — `User` tidak implement `MustVerifyEmail` sehingga middleware tidak pernah berefek (dead config yang menyesatkan pembaca). **Fix**: dihapus dari route `dashboard` dan group event. Jika email verification klasik diaktifkan kelak, implement `MustVerifyEmail` + pasang kembali middleware secara atomik.
7. **Dead Code `app/Http/Controllers/EventController.php` (LOW)** — Controller Blade-style tanpa referensi route, view tidak ada, inline validation (pelanggaran konvensi Form Request). **Fix**: dihapus setelah verifikasi zero references.

### Round 3 (meeting link — deferred entry & delivery)

8. **Meeting Link Wajib saat Create (UX GAP)** — Online/hybrid event sebelumnya wajib punya link saat dibuat, memaksa organizer menebak link sebelum event disiapkan. **Fix**: `meeting_link` jadi opsional (`nullable|url`) + opsi "Masukkan Nanti" di form; link diisi belakangan dari halaman My Events (`PATCH /dashboard/events/{event}/meeting-link`, policy `update`).
9. **Tidak Ada Mekanisme Pengiriman Link ke Peserta (UX GAP)** — **Fix**: `POST /dashboard/events/{event}/meeting-link/send` (`throttle:5,1`, policy `update`, guard link terisi) → email ter-queue (`MeetingLinkNotification`, ShouldQueue, locale id/en) ke peserta ber-confirmed-booking, dedupe per user; WhatsApp ter-log (`WA_MEETING_LINK_SENDING`) mengikuti pola OTP sampai provider terpasang.
10. **Property Collision Mailable (LOW)** — `$locale` readonly di `MeetingLinkNotification` menabrak property `Mailable::$locale` (fatal saat queue serialize). Terdeteksi saat eksekusi suite. **Fix**: rename → `$mailLocale` private.

### Round 4 (audit ulang fitur meeting link)

11. **Meeting Link Bisa Di-set di Event Offline (MEDIUM)** — `UpdateMeetingLinkRequest` tidak memeriksa tipe event; API memungkinkan link meeting pada event offline (UI tidak menampilkannya, tapi endpoint terbuka). **Fix**: `withValidator` menolak update untuk event `offline` → error validasi, DB tidak berubah (tertest ML-05).
12. **Kirim Link untuk Event Dibatalkan / Sudah Berakhir (MEDIUM)** — Send tidak punya guard status/waktu; organizer bisa broadcast link event yang dibatalkan atau sudah selesai. **Fix**: guard `sendGuardError` — status `cancelled` atau `end_date` lewat → flash error, nol email ter-queue (tertest ML-06/ML-07).
13. **Double-Click Send → Email Ganda (MEDIUM)** — `throttle:5,1` menghitung request, bukan serialisasi; dua request concurrent dalam detik yang sama lolos bersamaan → peserta menerima email dobel. **Fix**: `Cache::lock('event-link-send:{eventId}', 15s).block(3s)` di `EventService::sendMeetingLinkToAttendees`; `LockTimeoutException` → flash error ter-lokalize (pola sama dengan create). Re-send sekuensial tetap diizinkan sebagai aksi eksplisit organizer.
14. **PATCH Meeting Link Tanpa Throttle (LOW)** — Route update tidak ber-rate-limit (berbeda dengan store & send). **Fix**: `throttle:10,1`; request ke-11 → 429 (tertest).
15. **Label Waktu Email Bohong (MEDIUM)** — Mailable hardcode sufiks ` WIB` padahal `app.timezone = UTC` → peserta membaca jam yang salah dengan label Jakarta. **Fix**: render `setTimezone(config('app.timezone'))->format('d M Y H:i T')` — singkatan timezone aktual, selalu konsisten dengan konfigurasi.
16. **Hitungan Channel Duplikat Antar Layer (LOW)** — Service menghitung `emailed` dari `$hadEmail` sebelum kirim, sementara keputusan kirim ada di `NotificationService` — dua sumber kebenaran yang bisa drift. **Fix**: `sendMeetingLink` mengembalikan channel yang benar-benar terpakai (`array{emailed, whatsapped}`); service menjumlahkan dari return tersebut (tertest WB-07/WB-08).
17. **My Events Tanpa Pagination (LOW-MEDIUM)** — `listForOrganizer` memuat semua event organizer sekali jalan; payload Inertia membengkak di akun produktif. **Fix**: `paginate(10)` + meta (`current_page/last_page/per_page/total`) ke frontend; kontrol prev/next di Index (tertest ML-08).

### Round 5 (landing page publik & lifecycle actions)

18. **Service Update/Delete Menyentuh Eloquent Langsung (MEDIUM, DEBT)** — `updateEvent`/`deleteEvent` menjalankan `DB::transaction` + `$event->update()/delete()` di Service (melanggar pola Service→Repository yang dipakai `createEvent`). **Fix**: `EventRepository::update()`/`softDelete()`; Service hanya menangani side-effect filesystem (banner) + business guard.
19. **Exception Generik di Delete Guard (LOW)** — `deleteEvent` melempar `\Exception` umum sehingga controller tidak bisa membedakan jenis kegagalan. **Fix**: `InvalidArgumentException` bertipe + catch spesifik di controller → flash error ter-lokalisasi.
20. **Payload Landing Tidak Lengkap (MEDIUM, INTEGRATION GAP)** — `getPublicEventData` tidak menyertakan `slug`, `latitude`, `longitude`, dan `organizer` yang dibutuhkan `Events/Show.tsx`; halaman ter-render dengan organizer/koordinat `undefined`. Ditemukan lewat verifikasi kontrak lintas-layer (bukan oleh assertion lama). **Fix**: lengkapi payload + `loadMissing('user')` + assertion kontrak di test (EM-01). Bukti live: `GET /events/xyz-music-festival` → HTTP 200 dengan `organizer:{"id":1,"name":"Roy Nobeel"}`.
21. **Form Create/Edit Duplikatif (LOW, DRY)** — form create 400+ baris tidak bisa dipakai ulang oleh halaman edit. **Fix**: ekstraksi `Components/EventForm.tsx` (initial values, method post/patch, banner existing + replace, dsb.) dipakai Create & Edit.
22. **Slug Hijack via Update (SECURITY, PREVENTED)** — payload update yang menyertakan `slug` tidak boleh mengubah URL publik event. **Fix/verifikasi**: `slug` tak pernah masuk `UpdateEventRequest::validated()`; ditambah test eksplisit (EM-03).

### Round 6 (landing: sticky navbar, zona waktu, reputasi & peserta)

23. **Waktu Acara Salah — Akar Masalah (HIGH)** — Perbaikan sebelumnya hanya mengubah *label* email; akar masalahnya `config('app.timezone') = 'UTC'` sehingga jam dinding yang diisi organizer ditafsirkan sebagai UTC dan bergeser 7 jam saat ditampilkan. **Fix**: `APP_TIMEZONE=Asia/Jakarta` (env + `.env.example`), prop Inertia `timezone` di-share, dan frontend memformat semua timestamp dengan `timeZone` eksplisit. Tercatat `config/app.php` memakai `env('APP_TIMEZONE', 'Asia/Jakarta')`. Data lama (jam dinding naif) kini tampil sesuai yang diisi. Tercetak bukti: payload ISO ber-offset `+07:00` (RE-13).
24. **Tidak Ada Rating Penyelenggara (FEATURE GAP)** — Tidak ada tabel/relasi penilaian. **Fix**: migrasi `event_reviews` (+ unique `[event_id,user_id]`), model `EventReview`, `Event::reviews()`, `ReviewRepository` (agregat avg/count, exists, create), `ReviewService` (eligibility + submit), endpoint `POST /events/{event}/reviews` (`auth` + `throttle:10,1`), UI form rating + tampilan milik sendiri. Eligibility: peserta ber-booking confirmed, event selesai, bukan milik sendiri, belum pernah menilai (tertest RE-01–RE-08).
25. **Peserta Terdaftar Tidak Terlihat (FEATURE GAP)** — **Fix**: `attendees { count, avatars[] }` — hitungan booking confirmed (distinct user) + avatar stack pendaftar awal. Foto profil bila ada (`social_avatar` URL / `avatar` disk publik), jika tidak → **inisial**. **Privasi**: payload publik hanya mengirim inisial, bukan nama lengkap (tertest RE-11).
26. **Sticky Navbar Tanpa Identitas Event (UX GAP)** — Navbar sticky hanya berisi tombol kembali/share/edit. **Fix**: nama event + tanggal/jam ditampilkan (fade-in) saat scroll > 140px, tetap dengan aksi kembali/share/edit.
27. **Logika Landing di EventService (DEBT)** — `getPublicEventData` menumpuk di `EventService` (fokus lifecycle) dan mulai bertabrakan dengan perubahan paralel (kunci `organizer` duplikat). **Fix**: ekstraksi `EventLandingService` (EventRepository + BookingRepository + ReviewService) — EventService kembali fokus lifecycle; payload landing satu sumber.

---

## 🏗️ 4. Evaluasi Arsitektur (CTO Checklist)

| Kriteria | Penilaian | Detail |
| :--- | :---: | :--- |
| **Controller tipis** | ✅ | `Web\EventController` hanya routing + delegasi; < 15 baris per method. |
| **Service-Repository** | ✅ | Orchestration di `EventService`; persistensi & slug hanya di `EventRepository`. Service bebas Eloquent untuk flow create → unit-testable tanpa DB. |
| **Native Laravel** | ✅ | Form Request (bukan inline validate), `Gate`, middleware stack di `bootstrap/app.php`. |
| **Query & N+1** | ✅ | Flow create: 1 SELECT prefix-slug (index `slug`, range scan) + 1 INSERT. Tidak ada relation loading — N+1 tidak mungkin di path ini. |
| **Race Condition** | ✅ | Slug: `DB::transaction` + `lockForUpdate` (termasuk soft-deleted rows via `withTrashed`) + retry random suffix sebagai last resort. |
| **Idempotency & Double-Submit** | ✅ | `Cache::lock` per-organizer (15s TTL, 5s wait) + natural-key dedupe 60s di dalam lock; image store setelah dedupe (zero orphan). |
| **Rate Limiting** | ✅ | `throttle:10,1` pada `events.store` (EV-11). |
| **Security** | ✅ | Dual policy check, `validated()` only, normalisasi `'' → null`, MIME+size image, CSRF via Inertia. |
| **Frontend Alignment** | ✅ | Tailwind v4 CSS-first, komponen shared (`Input`/`Button`/`Textarea`/`Select`), i18n `event-texts.ts` (id/en) via prop `locale`, Ziggy `route()`. |
| **Code Bloat** | ✅ | Zero duplikasi; `Textarea`/`Select` dibuat reusable di `Form.tsx`. |

---

## 🛠️ 5. Outstanding Items (Rekomendasi)

Semua temuan round 1 telah diremediasi. Catatan lanjutan:

1. **Cache Lock Driver (INFO)** — Guard double-submit bekerja pada semua lock-capable cache store. Saat ini dev memakai `database` (tabel `cache_locks` sudah termigrasi), test memakai `array`. Jika `CACHE_STORE` diganti ke driver non-lock-capable (mis. `file` versi lama / `dynamodb` tanpa konfigurasi), lock terdegradasi senyap — tambahkan smoke test terhadap driver produksi bila berganti.
2. **Email Verification Klasik (INFO)** — Jika suatu saat `MustVerifyEmail` diimplement pada `User`, pasang kembali middleware `verified` di route dashboard/event secara bersamaan; audit dulu dampaknya ke user OTP-phone (email `null`) agar tidak ter-403 dari dashboard.
3. **Halaman Publik Event (SCOPE)** — Listing (`events.index`) dan detail event belum ter-wire ke route Inertia; menjadi prasyarat fitur tiket & booking.

---

## 📚 Referensi

- Dokumentasi fitur: [`docs/features/EVENT_MANAGEMENT.md`](../features/EVENT_MANAGEMENT.md)
- Permission matrix: [`docs/rbac/RBAC_IMPLEMENTATION.md`](../rbac/RBAC_IMPLEMENTATION.md)
- Skema tabel `events` diverifikasi langsung dari MySQL `eventslab` (bukan dari README saja).
