# Admin Panel

Platform administration: 2FA login, organizer impersonation, gateway/provider settings, catalog management (categories, cities), platform fee, and WhatsApp packages.

## Access & Authentication

Admin routes live under `/admin` and require the `admin` role (`EnsureAdmin` middleware alias `admin`).

- **Two-factor login:** `/admin/login` (email + password) → `/admin/otp` (6-digit OTP) → session. Password is verified with `Hash::check`; only accounts with the `admin` role can start the flow. The OTP reuses the existing `AuthService` pipeline (logged locally until a provider is configured).
- **Resend / alternative delivery:** the OTP page offers **Resend via Email** and **Send via WhatsApp** (when the admin has a phone) via `POST /admin/otp/resend` (`channel: email|whatsapp`, `throttle:5,1`, 60s client cooldown). The code is always stored on the same admin user, so verification by email works regardless of the delivery channel.
- **Separation:** the public passwordless login (`/login`) is unchanged; admin auth is a distinct, stricter entry point.
- **Seeded admin (dev):** `AdminSeeder` creates `admin@event-management.test` / `password` with the `admin` role.

```
GET  /admin/login     admin.login          (guest)
POST /admin/login     admin.login.store    [throttle:5,1]
GET  /admin/otp       admin.otp            (guest)
POST /admin/otp       admin.otp.verify     [throttle:5,1]
POST /admin/otp/resend admin.otp.resend    [throttle:5,1] channel=email|whatsapp
POST /admin/logout    admin.logout         [auth, admin]
```

## Impersonation

Admins can log in as an organizer to support/debug without their credentials.

```
POST /admin/impersonate/{user}   admin.impersonate   [auth, admin]  (organizers only — admins blocked)
POST /impersonation/stop         impersonation.stop  [auth]         (available to the impersonated session)
```

- The original admin id is kept in the session (`impersonator_id`); `HandleInertiaRequests` shares `impersonating` so the UI shows a banner + "back to admin".
- Starting regenerates the session; stopping restores the admin account.

## Platform Settings

`settings` table (key/value, JSON-encoded) behind `SettingsRepository` + `SettingsService`. Secrets are never returned to the UI — only a set/not-set flag; a blank secret on update keeps the stored value. The page uses **tabs** (General / Payment Gateway / WhatsApp / Platform Fee).

```
GET /admin/settings                    admin.settings            (tabbed UI)
PUT /admin/settings/general            admin.settings.general    app_name, contact_center, contact_email
PUT /admin/settings/payment-gateway    admin.settings.payment    provider, api_key, secret_key, enabled
PUT /admin/settings/whatsapp           admin.settings.whatsapp   provider, token, sender, enabled
PUT /admin/settings/platform-fee       admin.settings.fee        type (fixed|percent), amount
```

- **General:** app name (`general.app_name`, falls back to `config('app.name')`), contact center, contact email.
- **Payment gateway:** provider + API/secret keys stored (`payment_gateway.*`).
- **WhatsApp provider:** provider + token + sender (`whatsapp_provider.*`).
- **Platform fee:** `fixed` or `percent`; applied at booking time to the ticket subtotal — stored on `bookings.platform_fee` and included in `bookings.total_amount` (default 0). `SettingsService::platformFeeFor($base)` is the single calculation point.

## Catalog Management

All admin-only, Inertia CRUD with inline edit/delete:

```
/admin/categories   → categories (slug, name, name_en, is_active)   — drives the event form category select
/admin/cities       → cities (name, is_active)                      — drives the event form city select
/admin/packages     → whatsapp_packages (slug, label, quota, amount, is_active) — drives quota top-up
```

- Categories/cities are seeded (`CategorySeeder` from the `EventCategory` enum defaults, `CitySeeder` with common Indonesian cities, `WhatsAppPackageSeeder` from `config/whatsapp.php`).
- `Event` has a `categoryModel()` relation (`belongsTo Category` by slug); category labels are resolved from the table with a raw-slug fallback.
- `WhatsAppQuotaService::packages()/topUp()` read active `whatsapp_packages` rows, so admin edits immediately affect what organizers can buy.

## Analytics

`GET /admin/analytics` (`admin.analytics?period=&year=`) renders `Admin/Analytics` from `AdminAnalyticsService::forPeriod($period, $year)`; period switches between `week` (12 weeks), `month` (12 months of the selected year) and `year` (5-year trend ending at the selected year), and a **year selector** (`available_years`) scopes every series and total to that year.

- **Event volume:** events created per bucket (weekly / monthly / yearly).
- **Revenue split:** gross, **organizer revenue** (`total_amount − platform_fee`) and **platform revenue** (`platform_fee`) per bucket, from paid bookings.
- **Category breakdown:** per-category events created, paid bookings, gross revenue, platform cut and share of total revenue (joined to the admin-managed `categories` table for locale-aware labels).
- **Event leaderboards:** most popular (most confirmed bookings + revenue), worst performing (published events with fewest confirmed bookings), cancelled events.
- **Audience origin:** visitor countries + cities (top 10) from `event_visits`.
- **Devices + OS:** mobile / tablet / desktop / bot shares and the operating system (ios / android / windows / macos / linux / chromeos / other).
- **Realtime (`realtime` prop):** visitors active in the last 5 minutes — active + unique counts, breakdown by country / city / device / OS, and a recent-hits feed with **masked IP** (`x.x.x.x`). The page polls `router.reload({ only: ['realtime', 'daily'] })` every 30s.
- **Daily (`daily` prop):** visits + unique visitors per day for the last 14 days (zero-filled), plus device/OS totals for the window.

### Layout (Google Analytics-style)

Property header with the period control (Weekly / Monthly / Yearly) top-right, a row of scorecards (Gross / Organizer / Platform revenue, Events) each with a sparkline, a primary **area chart card** with metric tabs (`#1a73e8` accent), then two-column cards for category breakdown + visitor countries, ranked leaderboards, and cities + devices. GA palette (blue `#1a73e8`, green `#34a853`, amber `#f9ab00`, purple `#9334e6`), 8px radii, hairline borders, `tabular-nums`, inline bars — no gradients, emoji or repetitive identical card grids.

### Visitor tracking

```
EventController@show (public, non-owner) → VisitTrackingService::record(request, event)
    ip      ← $request->ip()   (proxy-aware: trustProxies('*') reads X-Forwarded-For)
    device  ← User-Agent classification (no dependency)
    os      ← User-Agent classification (ios/android/windows/macos/linux/chromeos/other)
    country/city ← IpGeolocationService (optional, disabled by default)
→ event_visits (event_id, ip, country, city, device, os, user_agent)
```

- **Proxy / masked IP:** `bootstrap/app.php` calls `trustProxies(at: '*')`, so behind nginx/Cloudflare `$request->ip()` resolves the real client IP from `X-Forwarded-For`. This is intentionally permissive — restrict `at:` to your LB ranges in production.
- **Geolocation:** `config/analytics.php` (`GEOIP_ENABLED`, `GEOIP_ENDPOINT` with `{ip}`, `GEOIP_TIMEOUT`). Disabled by default so no external call happens until a provider is configured; private/reserved IPs are never sent. Country/city simply stay null when disabled.
- **Privacy:** owner/admin views are not tracked; raw IP + UA are stored server-side only and aggregated in the dashboard.

## Users

```
GET    /admin/users                      admin.users          [auth, admin]  search + role/status filter + pagination
POST   /admin/users/{user}/suspend       admin.users.suspend
POST   /admin/users/{user}/activate      admin.users.activate
DELETE /admin/users/{user}               admin.users.destroy
POST   /admin/impersonate/{user}         admin.impersonate
```

- **Search / filter / pagination:** `AdminDashboardService::users()` supports `search` (name/email `like`), `role` (spatie `role()` scope) and `status` (`active` / `suspended`), paginated 15/page with `withQueryString()`.
- **Suspend:** sets `users.suspended_at`. `EnsureAccountIsActive` (web middleware) logs a suspended user out and redirects to login with a flash error on their next request. Suspended users cannot be impersonated.
- **Delete:** soft delete (`User` uses `SoftDeletes`).
- **Guards:** an admin cannot suspend/activate/delete themselves or another admin (403).
- **Impersonate:** organizers only, and never suspended users. Session stores `impersonator_id`; the UI shows a banner and "back to admin" (`/impersonation/stop`).

## Frontend

- Pages: `resources/js/Pages/Admin/{Login,VerifyOtp,Dashboard,Users,Settings,Categories,Cities,Packages}.tsx`, shared `resources/js/Layouts/AdminLayout.tsx`.
- Copy: `resources/js/config/admin-texts.ts` (id/en).

## Testing

- `tests/Feature/AdminAuthTest.php` (9) — public login page, wrong password, non-admin rejected, password→OTP gate, wrong OTP, resend via email, resend via WhatsApp (requires phone), panel role guard.
- `tests/Feature/ImpersonationTest.php` (4) — impersonate organizer, block admin→admin, stop restores admin, non-admin 403.
- `tests/Feature/AdminPanelTest.php` (7) — panel authorization, settings snapshot, payment update + blank-secret retention, platform fee computation, category CRUD, city create, package CRUD → `WhatsAppQuotaService`.
- `tests/Feature/AdminAnalyticsTest.php` (9) — panel guard, default/month + period switch + year selection, gross/platform/organizer revenue split, category breakdown, most-popular / worst-performing / cancelled leaderboards, countries/cities/devices aggregation, realtime (last 5 min) + daily trend.
- `tests/Feature/VisitTrackingTest.php` (4) — guest visit recorded with device + OS, owner view not tracked, UA device classification, UA OS classification.
- `tests/Feature/AdminUserManagementTest.php` (8) — search, role filter, status filter, pagination, suspend+activate (and next-request logout), self/admin guards, soft delete, suspended-user impersonation blocked.
