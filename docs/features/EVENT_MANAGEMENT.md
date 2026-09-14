# Event Management - Create Event Feature

## Feature Overview

Organizers and admins can create events from the dashboard (`/dashboard`) via a dedicated form page (`/dashboard/events/create`). The form captures every column of the `events` table (see schema below). Creation supports three event types (offline / online / hybrid), draft or publish-on-create status, optional banner upload, and optional geo-coordinates.

**Ticket settings are part of the create flow.** At least one ticket type must be defined when an event is created; the event row and its ticket rows are persisted atomically inside a single database transaction. Tickets can also be reconciled later from the Edit form or via the event-scoped ticket endpoints.

### `events` table → form field mapping

| Column | Form field | Validation |
|---|---|---|
| `title` | Event name | required, string, max 255 |
| `slug` | *(generated)* | race-safe unique (see below) |
| `description` | Description textarea | required, string, max 5000 |
| `image` | Banner file input | nullable, image, max 2048 KB |
| `type` | Type card selector | required, `online`/`offline`/`hybrid` |
| `category` | Category select | required, `exists:categories,slug` (admin-managed; seeded from `App\Enums\EventCategory`); DB default `other` |
| `city` | City select | optional, string max 100 (options from admin-managed `cities`) |
| `venue_name` | Venue name | required unless `type=online` (default `Online Event`) |
| `venue_address` | Full address textarea | required unless `type=online` (defaults to meeting link) |
| `latitude` / `longitude` | Collapsible location pin | nullable, numeric, bounded ±90 / ±180 |
| `meeting_link` | Meeting link (shown for online/hybrid) | optional — `nullable, url, max 255`; can be deferred and added later from My Events (see below) |
| `start_date` / `end_date` | datetime-local inputs | required, `start > now`, `end > start` |
| `capacity` | Capacity number input | nullable, integer, min 1 |
| `status` | Draft / Publish selector | required, `draft`/`published` (`cancelled` not allowed at creation) |
| `user_id` | *(from session)* | owner = authenticated organizer |

### `tickets` table → nested form fields (`tickets.*`)

| Column | Form field | Validation |
|---|---|---|
| `name` | Ticket name | required, string, max 255, `distinct` across rows |
| `description` | Ticket description | nullable, string, max 1000 |
| `price` | Price (Rp) | required, numeric, min 0 (`0` = free) |
| `quantity` | Quota | required, integer, min 1 |
| `sale_starts` / `sale_ends` | Collapsible sale window | nullable, date; `sale_ends > sale_starts` (cross-field check) |
| `min_per_order` / `max_per_order` | Limits per order | required, integer, min 1; `max >= min` (cross-field check) |
| `is_active` | "Ticket is on sale" toggle | boolean (defaults `true`) |
| `id` | *(hidden, edit only)* | nullable integer — present ⇒ update, absent ⇒ create |

`tickets` itself: `required | array | min:1 | max:50` on create, `nullable | array | min:1 | max:50` on update (absent ⇒ tickets untouched).

## Architecture Blueprint

```
GET  /dashboard/events/create   → Web\EventController@create
                                  → Gate::authorize('create', Event) [EventPolicy: admin|organizer]
                                  → Inertia::render('Events/Create')

POST /dashboard/events          → Web\EventController@store (thin) [throttle:10,1]
                                  → StoreEventRequest (validation + policy authorization + nested ticket rules)
                                  → EventService::createEvent(validated, userId)
                                      → Cache::lock('event-create:{userId}', 15s).block(5s)
                                          → applyOnlineVenueDefaults()
                                          → EventRepository::findRecentDuplicate()  ← natural-key idempotency (60s window)
                                              → duplicate found → return existing event (no image store, no tickets)
                                          → image store on public disk
                                          → EventRepository::create()  ← DB::transaction
                                                → generateUniqueSlug (lockForUpdate)
                                                → Event::create (+ UniqueConstraintViolation retry)
                                                → event->tickets()->createMany(tickets.*)  ← atomic with event
                                  → redirect /dashboard + flash message (locale-aware)
                                  → LockTimeoutException → back to form + flash error
```

- **Controller** (`app/Http/Controllers/Web/EventController.php`): HTTP routing only, no business logic.
- **Service** (`app/Services/EventService.php`): orchestration — per-organizer distributed lock, natural-key idempotency decision, image handling, online-venue defaults, ownership assignment. No direct Eloquent for creation.
- **Repository** (`app/Repositories/EventRepository.php`): sole owner of event persistence, slug generation (inside `DB::transaction`), and duplicate detection.

## API & Inertia Props Contract

- `Events/Create` page receives no custom props (uses shared `auth.user.roles`, `locale`, `ziggy` from `HandleInertiaRequests`).
- Submit posts multipart form data (banner upload) to `route('events.store')`.
- Validation errors arrive via Inertia's `errors` prop and render inline per field.
- Success redirects to `/dashboard` with `flash.message` (rendered as banner on the Dashboard page).
- The Dashboard "Create Event" button is rendered only when `auth.user.roles` includes `admin` or `organizer`.

## Meeting Link — Deferred Entry & Attendee Delivery

Online/hybrid events may be created **without** a meeting link ("Masukkan Nanti" option in the form). The organizer completes and distributes it later from **My Events**:

```
GET   /dashboard/events                          → Events/Index (My Events, paginated 10/page, lean rows + pagination meta)
PATCH /dashboard/events/{event}/meeting-link     → [throttle:10,1] UpdateMeetingLinkRequest
                                                    → policy: update (owner/admin)
                                                    → type guard: rejected for offline events
                                                    → EventRepository::updateMeetingLink
POST  /dashboard/events/{event}/meeting-link/send → [throttle:5,1] policy: update
                                                    → guards (locale-aware flash errors):
                                                        link unset | status cancelled | end_date past
                                                    → Cache::lock('event-link-send:{eventId}', 15s).block(3s)
                                                    → EventRepository::confirmedAttendeesForEvent (distinct users)
                                                    → NotificationService::sendMeetingLink per user
                                                        returns channels actually used {emailed, whatsapped}
                                                        email   → Mail::queue(MeetingLinkNotification, ShouldQueue)
                                                        phone   → WhatsApp log payload (provider-ready hook)
                                                    → LockTimeoutException → flash error (no duplicate broadcast)
                                                    → flash: "sent to X email(s) and Y WhatsApp number(s)"
```

- **Mailable:** `app/Mail/MeetingLinkNotification.php` (queued, locale-aware id/en, markdown view `resources/views/emails/events/meeting-link.blade.php`). Event time rendered in `app.timezone` with the real timezone abbreviation — never hardcoded.
- **WhatsApp:** logged as `WA_MEETING_LINK_SENDING` — same pattern as the OTP flow until a provider (Fonnte/Twilio) is integrated; callers stay untouched when it lands.
- **Recipients:** only users with a **confirmed** booking on that event, deduplicated per user (one user with two bookings gets one email).
- **Concurrency:** the send broadcast is serialized per event with a distributed lock — a double click cannot queue duplicate notifications; sequential re-sends remain explicit organizer actions.

### Events/Index props contract

```ts
events: Array<{
    id: number;
    title: string;
    slug: string;
    type: 'online' | 'offline' | 'hybrid';
    status: 'draft' | 'published' | 'cancelled';
    start_date: string;   // ISO 8601
    end_date: string;
    meeting_link: string | null;
    confirmed_bookings: number;  // withCount, confirmed scope
}>;
pagination: {
    current_page: number;
    last_page: number;
    per_page: number;   // 10
    total: number;
};
```

## Public Landing & Event Actions

Every event row in **My Events** exposes full lifecycle actions, and each event has a public landing page:

```
GET    /events/{slug}                        → events.show (PUBLIC)
                                                published → everyone; draft/cancelled → owner/admin only (404 otherwise)
                                                props: { event (lean incl. tickets/attendees/organizer), is_owner }
POST   /events/{event}/reviews               → events.reviews.store [auth, throttle:10,1] StoreReviewRequest
                                                eligibility in ReviewService: confirmed booking + event ended + not cancelled
                                                + not the owner + one review per user
```

### Landing page behaviour

- **Sticky navbar:** reveals the event **name + date/time** once the page is scrolled (scroll > 140px), while keeping back/share/edit actions available.
- **Timezone (root-cause fix):** `APP_TIMEZONE=Asia/Jakarta` (config `app.timezone`), exposed to the frontend as the shared Inertia prop `timezone`. All landing timestamps are formatted with an explicit `timeZone`, so wall-clock times entered by organizers render exactly as intended regardless of the viewer's browser timezone.
- **Registered attendees:** `event.attendees = { count, avatars[] }` — count of **confirmed** bookings (distinct users) and an avatar stack of the first registrants. Avatars resolve to the profile photo (`social_avatar` URL or public-disk `avatar`) or fall back to **initials**. Only initials are sent publicly — full attendee names never leave the server.
- **Organizer reputation:** `event.organizer.rating = { average, count }` aggregated from all reviews across the organizer's events, plus `past_events` (their published, already-ended events, excluding the current one).
- **Reviews:** attendees review finished events once. `event.can_review` drives the form; `event.my_review` renders the submitted rating read-only. Comments are stored but only the aggregate is shown publicly.

### Reviews data model

- Table `event_reviews` (`event_id`, `user_id`, `rating` 1–5, `comment`, unique `[event_id, user_id]`).
- `ReviewService` (eligibility + submit) ← `ReviewRepository` (aggregate/exists/create) ← `EventReview` model + `Event::reviews()`.

## Booking → Attendees → Payment → QR Delivery

Attendees pick quantities on the landing page, then go to a **transaction page** to capture participant names (one by one, or a single representative) and a recipient contact, then to **checkout** (payment method). Emails are sent at booking time and again on successful payment with the QR codes.

**Seamless (no login):** guests can complete the entire purchase without an account. A passwordless attendee account is resolved/created from the contact email so bookings keep a real `user_id` (attendee counts, meeting-link delivery, dashboards all keep working) — but the buyer is never logged in. Checkout/receipt pages are reachable via a **signed URL** emailed to the contact address; the authenticated owner can also use the policy.

```
GET  /events/{event}/book                → bookings.create [throttle:20,1] BookingController@create  (PUBLIC)
                                              event published (403) + self-dealing guard (organizer → redirect + flash error)
                                              query: tickets[]={ticket_id,quantity}
                                              → BookingService::previewSelection (availability/min/max, no writes)
                                              → Inertia Bookings/Transaction { event, selection, total_amount }

POST /events/{event}/book                → bookings.store [throttle:10,1] StoreBookingRequest  (PUBLIC)
                                              contact.name + contact.email required (ticket/QR delivery)
                                              attendee_mode: individual | representative
                                              individual → tickets.*.attendees[].name required, count must equal quantity
                                              representative → representative.name required
                                              → BookingService::createBooking
                                                  resolveBuyer: auth id, else find-or-create passwordless attendee by email
                                                  SELF-DEALING GUARD: organizer == buyer → InvalidArgumentException
                                                  Cache::lock('booking-create:{event}:{buyer}') + DB::transaction
                                                  per-ticket lockForUpdate, availability/min/max, quantity_sold++
                                                  one booking_ticket row PER SEAT (quantity 1, unique ticket_code,
                                                  attendee_name / attendee_email)
                                              → email: BookingReceivedNotification (pending) or
                                                       BookingPaidNotification (free → settled instantly)
                                              → total > 0 → redirect signed bookings.pay  (checkout)
                                              → total == 0 → redirect signed bookings.show (confirmed)

GET  /bookings/{booking}                 → bookings.show  [booking.access:view] owner policy OR signed URL
GET  /bookings/{booking}/pay             → bookings.pay   [booking.access:pay]  owner policy OR signed URL; paid → redirect show
POST /bookings/{booking}/pay             → bookings.pay.store [booking.access:pay, throttle:10,1] StorePaymentRequest
                                              payment_method: required, Rule::enum(PaymentMethod)
                                              → BookingService::pay (cancelled → flash error; already paid → idempotent)
                                              → BookingRepository::markPaid (lockForUpdate + transaction → paid/confirmed)
                                              → email: BookingPaidNotification (QR attachments) + WhatsApp QR dispatch log
```

- **Seamless access control:** `booking.access` middleware (`app/Http/Middleware/BookingAccess.php`) authorizes checkout/receipt pages either through `BookingPolicy` (authenticated owner) **or** a valid signed URL (`URL::signedRoute`, emailed to the buyer). Unsigned guests get 403. Form Requests defer authorization to this middleware so signed guests can pay.
- **Selection querystring:** `Events/Show` sends the cart with `queryStringArrayFormat: 'indices'` so it serializes as `tickets[0][ticket_id]=..` — Inertia's default `brackets` format emits `tickets[][ticket_id]=..`, which PHP splits into single-key rows. `BookingController::normalizeSelections` additionally merges complementary consecutive rows, so both formats resolve correctly (regression-tested).
- **Guest identity:** `UserRepository::findOrCreateAttendeeByEmail` reuses an existing account or creates a passwordless attendee (random password, `attendee` role) under a row lock. The guest is not authenticated; access to their booking is via signed links in the emails. They can later claim the account through the existing magic-link/OTP login.
- **Attendee capture:** `attendee_mode=individual` requires one name per seat (validated server-side: count must equal quantity); `attendee_mode=representative` copies one representative name/email across all seats. Each seat is persisted as its own `booking_tickets` row so every ticket has an independent `ticket_code` and QR. The form always posts **both** branches (the inactive one with empty strings → `null`); the conditional `name` rules pair `requiredIf` with `nullable` so the inactive branch doesn't trip the base `string` rule.
- **Emails (queued):** `BookingReceivedNotification` (awaiting payment, links to checkout) and `BookingPaidNotification` (payment success, one QR PNG attached per ticket). Markdown views under `resources/views/emails/bookings/`. Locale-aware (id/en).
- **QR codes:** `TicketQrService` (endroid/qr-code + GD) renders a PNG per `ticket_code`. QR payload is the code itself, so any scanner reads it. Payment success attaches the PNGs to emails and dispatches the codes over WhatsApp when the event toggle is on and the organizer has quota (see “Ticket Delivery” below).
- **Payment methods:** `App\Enums\PaymentMethod` — `bank_transfer`, `virtual_account`, `ewallet`, `qris`, `credit_card`. Options (locale-aware labels) are sent to `Bookings/Pay`; the enum is the single source of truth for validation.
- **Owner-only payment:** `BookingPolicy::pay` restricts settlement to the booking creator (authenticated case); guests settle through the signed checkout link. `BookingPolicy::view` lets admins/staff/event organizers view without paying. Cross-user unsigned access is a 403 (feature-tested).
- **Self-dealing guard:** organizers cannot buy tickets for their own event — enforced in `BookingService::createBooking` and the transaction page (not just the UI); the landing page hides the buy affordance for the owner (`isBlockedOwner`). Guests and non-owner users can buy without logging in.
- **Concurrency/idempotency:** booking creation is serialized per `(event,user)` with a distributed lock + `lockForUpdate` on tickets; settlement re-locks the booking and short-circuits when already paid.
- **Payment gateway:** none wired. Selecting a method records the choice and settles the booking as a **simulated/mock payment** (clearly labelled in the UI). A real gateway drops into `BookingService::pay` / `BookingRepository::markPaid` without touching controllers.
- **Platform fee:** configured by admins (`/admin/settings`, fixed or percent). `BookingService` adds `SettingsService::platformFeeFor($subtotal)` to the order, stored on `bookings.platform_fee` and included in `bookings.total_amount` (default 0 — no effect until set).

## Ticket Delivery: Email & WhatsApp + Quota Top-Up

Every ticket can be delivered by **email and/or WhatsApp**. Attendees optionally supply an email and a WhatsApp number at checkout; the organizer enables WhatsApp delivery per event and funds it via a quota top-up in the dashboard.

```
Delivery (on payment success) — NotificationService::sendPaymentSuccess
  → buyer receipt email (all QRs attached)              BookingPaidNotification
  → per-attendee email for each distinct address        BookingPaidNotification (subset)
  → WhatsApp per ticket: attendee_phone rows,
      requires event.whatsapp_enabled == true
      and organizer quota; each send consumes 1 unit
      fallback: buyer phone when no attendee numbers
      log: WA_TICKETS_SENDING / WA_TICKETS_SKIPPED_NO_QUOTA

Organizer quota dashboard (admin/organizer only)
GET  /dashboard/whatsapp          → whatsapp.index   → Dashboard/WhatsApp { balance, packages, payment_methods, history }
POST /dashboard/whatsapp/topup    → whatsapp.topup   [throttle:10,1] StoreWhatsAppTopUpRequest
                                      package: Rule::in(config whatsapp.packages)
                                      payment_method: Rule::enum(PaymentMethod)
                                      → WhatsAppQuotaService::topUp → WhatsAppRepository::credit
                                          (transaction: top-up row + lockForUpdate quota increment)
                                      → flash + new balance
```

- **Data model:** `events.whatsapp_enabled` (bool), `booking_tickets.attendee_phone` (nullable), `users.whatsapp_quota` (unsigned int), `whatsapp_topups` (package, amount, quota, payment_method, status).
- **Packages:** `whatsapp_packages` table (admin-managed via `/admin/packages`, seeded from `config/whatsapp.php`: starter 100 / growth 500 / scale 2000). `WhatsAppQuotaService` reads active rows, so admin edits immediately change what organizers can buy.
- **Quota safety:** `WhatsAppRepository::consume` is a conditional atomic decrement (`where whatsapp_quota >= units`) → a send is skipped instead of going negative. Top-ups credit under a row lock.
- **Toggle:** `EventForm` exposes “Kirim Tiket via WhatsApp”; persisted through `Store/UpdateEventRequest` (`boolean`) and surfaced on `Events/Edit` as `event.whatsapp_enabled`.
- **Delivery payload:** the optional WhatsApp number is captured per attendee (`individual`) or once for the representative, and stored on each per-seat `booking_tickets` row.

## Event Categorization

Every event carries a category so listings, discovery and reporting can be filtered.

- **Source of truth:** the `categories` table (admin-managed via `/admin/categories`), seeded from `App\Enums\EventCategory` (`music`, `sports`, `technology`, `business`, `education`, `arts`, `food`, `community`, `other`).
- **Schema:** `events.category` (string, default `other`) + `events.city` (nullable). `Event::categoryModel()` relates by slug for locale-aware labels.
- **Validation:** `category` required on create/update (`exists:categories,slug`); `city` optional.
- **Form:** the shared `EventForm` renders category + city `Select`s; options come from active `categories`/`cities` passed as the `categories`/`cities` Inertia props from `EventController@create`/`@edit`.
- **Surfaced:** `Events/Index` rows and the `Events/Show` landing badge render `event.category_label`; the analytics payload includes category + city too.

## Event Analytics

Organizers track how an upcoming event is performing from a dedicated page (owner/admin only).

```
GET /dashboard/events/{event}/analytics → events.analytics [auth, profile.complete] Gate::authorize('update', event)
                                            → EventAnalyticsService::forEvent
                                                → EventAnalyticsRepository
                                                    bookingSummary   (counts by status + paid revenue, 1 query)
                                                    ticketTotals     (quota / sold / reserved, 1 query)
                                                    paidRevenueByTicket (revenue + paid qty per type)
                                                    checkedInCount   (non-cancelled, checked_in sum)
                                                    paidTimeline     (paid bookings/day, last 14 days)
                                            → Inertia Events/Analytics { event, summary, tickets, timeline }
```

- **KPIs:** revenue, tickets sold, sell-through % (sold ÷ quota), confirmed bookings, checked-in, remaining quota. Quota falls back to `event.capacity` when tickets carry none.
- **Per-ticket performance:** quota, sold, reserved, remaining, sell-through bar, and paid revenue per ticket type.
- **Sales trend:** paid bookings + revenue per day for the last 14 days, zero-filled so the chart has no gaps (portable `DATE(created_at)` grouping — MySQL & SQLite).
- **Authorization:** only the event owner or an admin (`EventPolicy::update`); non-owner organizers and attendees get 403, guests redirect to login.
- **Frontend:** `resources/js/Pages/Events/Analytics.tsx` — KPI cards, per-ticket progress bars, dependency-free CSS bar chart. Copy in `event-texts.ts` (`analytics` block, id/en). Reachable via the **Analytics** action on each row of My Events.

```
GET    /dashboard/events/{event}/edit        → events.edit (policy: update) → prefilled shared EventForm
PATCH  /dashboard/events/{event}             → events.update [throttle:10,1] UpdateEventRequest (policy: update)
                                                slug is NOT editable (never in validated payload)
PATCH  /dashboard/events/{event}/publish     → events.publish [throttle:10,1] guard: draft only
PATCH  /dashboard/events/{event}/cancel      → events.cancel [throttle:10,1] guard: not already cancelled
DELETE /dashboard/events/{event}             → events.destroy [throttle:10,1] guard: no active bookings
```

- **Events/Edit props contract** — `event` mirrors the create payload plus the current ticket rows and banner URL:
  ```ts
  event: {
      id; title; description; type; category; venue_name; venue_address; meeting_link;
      latitude; longitude; start_date; end_date; capacity; status; whatsapp_enabled;
      image_url: string | null;
      tickets: Array<{
          id: number; name: string; description: string | null;
          price: number; quantity: number;
          sale_starts: string | null; sale_ends: string | null;   // 'Y-m-d\TH:i'
          min_per_order: number; max_per_order: number; is_active: boolean;
      }>;
  }
  ```
- **Shared form:** `resources/js/Components/EventForm.tsx` powers both Create and Edit (DRY) — edit pre-fills values (including the ticket editor), shows the current banner with a replace affordance, and keeps the meeting-link "Add Later" option.
- **Status transitions:** explicit `publishEvent`/`cancelEvent` service methods enforce valid transitions (`draft→published`, `draft|published→cancelled`); invalid attempts surface as localized flash errors, never silent no-ops.
- **Delete guard:** soft-delete blocked while non-cancelled bookings exist (business rule in the service, typed `InvalidArgumentException`).
- **Repository discipline:** update/persist via `EventRepository::update`/`softDelete` — the service handles only image filesystem side effects and business guards.

### Ticket Settings — Create, Edit & Standalone Endpoints

Ticket rows are persisted through `app/Repositories/TicketRepository.php` (the only place ticket Eloquent is touched):

- **On create:** `tickets.*` travels inside the validated event payload; `EventRepository::create()` strips it and calls `Event::create` + `tickets()->createMany()` inside one `DB::transaction`, so a failing ticket row rolls back the whole event. The 60s natural-key idempotency window also prevents duplicate ticket rows on double submit.
- **On update:** when `tickets` is present, `EventService::updateEvent()` delegates to `TicketRepository::syncForEvent()` — rows with an `id` are updated (`lockForUpdate`), rows without an `id` are created, and rows missing from the payload are deleted. Absent `tickets` key ⇒ tickets untouched (backwards compatible).
- **Inventory guard:** a ticket's `quantity` can never drop below `quantity_sold + quantity_reserved`; violations throw `InvalidArgumentException` and surface as a localized flash error, leaving the row unchanged.
- **Deletion guard:** a ticket referenced by any non-cancelled booking cannot be deleted (prevents orphaning attendee entitlements via FK cascade).
- **Standalone endpoints (event-scoped):**

```
POST   /dashboard/events/{event}/tickets             → TicketController@store    [auth, profile.complete, throttle:10,1] StoreTicketRequest
PATCH  /dashboard/events/{event}/tickets/{ticket}    → TicketController@update   [throttle:10,1] UpdateTicketRequest
DELETE /dashboard/events/{event}/tickets/{ticket}    → TicketController@destroy  [throttle:10,1]
                                                          → policy: EventPolicy::update (owner/admin) via Form Request
                                                          → ticket must belong to the event (404 otherwise)
                                                          → EventService createTicket/updateTicket/deleteTicket → TicketRepository
```

- **Validation contract:** `ValidatesTicketFields` trait shares the field rules (`ticketFieldRules('tickets.*.')` for nested, `''` for flat) plus the cross-field checks (`max >= min`, `sale_ends > sale_starts`) that Laravel's wildcard rules cannot express.

### Events/Show props contract

```ts
event: {
    id: number; title: string; slug: string; description: string | null;
    type: 'online' | 'offline' | 'hybrid'; status: 'draft' | 'published' | 'cancelled';
    category: string | null; category_label: string | null;   // locale-aware
    image_url: string | null;           // public disk URL
    venue_name | venue_address: string | null;
    meeting_link: string | null;
    latitude | longitude: number | null;
    organizer: {
        id: number; name: string;
        rating: { average: number | null; count: number };
        past_events: Array<{ id, title, slug, type, image_url, start_date }>;
    } | null;
    start_date | end_date: string;      // ISO 8601 with app timezone offset
    capacity: number | null; is_full: boolean;
    attendees: { count: number; avatars: Array<{ initials: string; avatar_url: string | null }> };
    tickets: Array<{ id, name, description, price, remaining, min_per_order, max_per_order }>;
    can_book: boolean;   // authenticated viewer who is NOT the organizer
    can_review: boolean;
    my_review: { rating: number; comment: string | null; created_at: string | null } | null;
};
is_owner: boolean;
// shared (Inertia): locale, timezone ('Asia/Jakarta')
```

## Security & Resiliency Protocols

- **Authorization:** dual enforcement — `Gate::authorize` on the GET page and `StoreEventRequest::authorize()` (via `EventPolicy::create`) on POST. Attendees receive 403 on both. Route middleware: `auth`, `profile.complete` (`verified` intentionally omitted — see Known Follow-ups).
- **Mass assignment:** strictly `$request->validated()` only; `Event::$fillable` whitelist.
- **Double-submit / idempotency:** `Cache::lock('event-create:{userId}')` serializes concurrent creations per organizer (TTL 15s, max wait 5s; `LockTimeoutException` surfaces as a localized flash error). Natural-key idempotency: an identical event (user + title + type + schedule + venue) created within 60 seconds returns the existing row — duplicate submits never insert a second row or store an orphaned banner. Works on any lock-capable cache store (dev: `database` via `cache_locks` table; tests: `array`).
- **Rate limiting:** `throttle:10,1` on `POST /dashboard/events` and `PATCH .../meeting-link`; `throttle:5,1` on the meeting-link send route.
- **Meeting link authorization & guards:** both link routes enforce `EventPolicy::update` (owner or admin) — the Form Request authorizes via the route-bound model; the send action re-checks via `Gate::authorize`. Link updates are rejected for offline events (type guard in the Form Request). Sending requires the link to be set, the event not cancelled, and the event not ended (locale-aware flash error guards). The broadcast itself is serialized per event with `Cache::lock` so concurrent double-submits cannot queue duplicate notifications.
- **Race conditions (slug):** slug uniqueness is computed inside the creation transaction with `lockForUpdate()` over the slug prefix range; a `UniqueConstraintViolationException` retry with random suffix is the last line of defense. Soft-deleted events are included in the uniqueness check.
- **Data integrity:** creation runs in `DB::transaction` owned by the repository (event + tickets atomically); service stays side-effect-free for unit testing. Ticket updates/deletes run in `TicketRepository` transactions with `lockForUpdate` on reconciliation and guards for quantity reduction / active bookings.
- **Inventory race conditions:** ticket inventory is serialized end-to-end.
  - *Booking:* per-`(event,user)` distributed lock + `DB::transaction` + **`lockForUpdate` on the ticket row** before the availability/min/max check and `quantity_sold` increment — two buyers racing for the last seat can't oversell (the second re-reads the committed count).
  - *Ticket edit:* `TicketRepository::update`/`delete` re-read the row with `lockForUpdate` before guarding, so a stale in-memory model can't bypass `quantity >= sold + reserved` or the active-booking delete guard. `syncForEvent` locks existing rows on update and on delete.
  - *Payment:* `BookingRepository::markPaid` locks the booking and returns `null` when it is already paid — a concurrent double-submit settles once and never re-sends confirmation email / re-consumes WhatsApp quota.
  - *Quota:* `WhatsAppRepository::consume` is a conditional atomic decrement (`where whatsapp_quota >= units`), so balance never goes negative.
- **Ticket authorization:** every ticket endpoint re-checks `EventPolicy::update` in its Form Request; a ticket id from another event returns 404 (ownership is asserted on the event relation, never trusted from the payload). `id` in a create payload is ignored (`unset`) to prevent mass-assignment of foreign rows.
- **Input hygiene:** empty optional inputs are normalized to `null` in `prepareForValidation()`; image uploads restricted to image MIME types and 2MB.
- **Lifecycle authorization:** edit/update/publish/cancel/delete all enforce `EventPolicy::update`/`delete` (owner or admin) — verified by feature tests covering non-owner organizer and attendee 403s on every endpoint.
- **Slug immutability:** `slug` is never part of `UpdateEventRequest::validated()`, so a crafted payload cannot hijack or alter an event URL (covered by test).
- **Status transition guards:** publish only from `draft`, cancel only when not already cancelled — invalid transitions return a localized flash error instead of silently mutating state.
- **Delete guard:** soft-delete is blocked while non-cancelled bookings exist; the event (and its banner) remain intact on rejection.
- **Landing visibility:** draft/cancelled event pages 404 for everyone except the owner/admin — no accidental leak of unpublished content.
- **Review eligibility:** only confirmed attendees of a finished (non-cancelled) event may review, once; owners cannot review their own event. Enforced in `ReviewService` (not just UI).
- **Attendee privacy:** the public payload exposes attendee **initials only** (plus optional avatar image), never full names; counts include confirmed bookings only.

## Testing

- Feature: `tests/Feature/EventCreationTest.php` (30 tests) — guest redirect, 403 for attendees (page + POST), profile-incomplete redirect, Inertia page render, draft/online/published creation, banner storage, nested ticket persistence (multi-row, free/inactive), ticket required + per-row validation (name/price/quantity), `max >= min`, sale-window ordering, no duplicate tickets on double submit, category required/persisted + invalid rejected, WhatsApp delivery toggle persisted, full validation matrix, deferred meeting link (online/hybrid without link), duplicate slug handling, double-submit idempotency, rate limiting.
- Feature: `tests/Feature/MeetingLinkTest.php` (16 tests) — My Events page render + ownership isolation + pagination, set link (owner/non-owner/attendee/invalid URL/offline-type guard), send guards (link unset/cancelled/ended), delivery to confirmed attendees only (pending bookings and other-event attendees excluded), per-user dedupe, send + update throttle 429.
- Feature: `tests/Feature/EventManagementTest.php` (29 tests) — public landing visibility (published/draft/cancelled/unknown slug), landing payload contract (slug, coordinates, organizer, tickets), edit page authorization, update (slug immutability, banner replace deletes old file, banner kept without upload, validation), category toggle, stale-allocated ticket guard (fresh lock), ticket reconciliation (update/create/delete rows), quantity-reduction guard, active-booking deletion guard, standalone ticket endpoints (add/update/delete, 403 non-owner, 404 cross-event, guest redirects), publish/cancel transitions + guards, delete with/without active bookings, guest redirects on every lifecycle endpoint.
- Unit: `tests/Unit/BookingConcurrencyTest.php` (1 test) — `markPaid` settles once and returns null on a racing double-submit (no duplicate delivery).
- Feature: `tests/Feature/EventReviewTest.php` (13 tests) — guest block, eligibility matrix (confirmed booking required, ended only, cancelled excluded, owner excluded, duplicate rejected), rating bounds 1–5, landing `can_review`/`my_review`, organizer rating aggregate, past-events exclusion of current/future, registered count + initials-only avatars, app-timezone ISO offset.
- Unit: `tests/Unit/EventServiceTest.php` (16 tests) — repository delegation, duplicate short-circuit, online venue defaults, venue passthrough, image path contract, ticket payload passthrough on create, ticket sync on update (and no-op when absent), createTicket delegation, meeting link delegation, per-user notify + channel counts, lean list mapping, update delegation (image/no-image), banner replacement, publish/cancel transition guards.
- Feature: `tests/Feature/BookingPaymentTest.php` (23 tests) — **seamless guest checkout (buy + pay without login, passwordless account resolved by email)**, unsigned guest 403, transaction page listing + organizer block, individual/representative attendee capture (per-seat rows, unique codes, count validation, attendee phone persisted), booking-received email queue, payment-success email queue, free-booking instant confirmation, WhatsApp delivery consumes quota / skipped when event disabled / skipped at zero quota, organizer self-dealing block, paid booking → signed payment redirect, owner-only pay page, cross-user 403 (show/pay/process), settlement records method + confirms, invalid method rejected, cancelled booking cannot pay, already-paid idempotency.
- Feature: `tests/Feature/EventAnalyticsTest.php` (4 tests) — guest redirect, owner sees KPIs/per-ticket/timeline with correct aggregates, non-owner organizer 403, attendee 403.
- Feature: `tests/Feature/WhatsAppQuotaTest.php` (5 tests) — guest redirect, attendee 403, organizer quota dashboard render, top-up credits quota + records ledger, validation rejects unknown package/method.
- Unit: `tests/Unit/WhatsAppQuotaServiceTest.php` (4 tests) — top-up credits + history, atomic consume/refuse-at-zero, unknown package rejected, locale package prices.
- Unit: `tests/Unit/TicketQrServiceTest.php` (3 tests) — PNG binary generation, data URI, one QR attachment per ticket on the paid mailable.
- Unit: `tests/Unit/NotificationServiceTest.php` (3 tests) — queued email for email users, WhatsApp log payload, no-op for channel-less users.
- Full QA & security audit report with test evidence, vulnerability fix history, and outstanding items: [`docs/qa/EVENT_CREATION_QA.md`](../qa/EVENT_CREATION_QA.md).

## Known Follow-ups (Tech Debt)

- ~~Legacy `app/Http/Controllers/EventController.php` dead code~~ — removed.
- ~~`verified` middleware inert~~ — removed from `dashboard` and event routes. `User` does not implement `MustVerifyEmail` (unified OTP/magic-link auth), so the middleware could never fire; keeping it was misleading dead config. Re-add only together with `MustVerifyEmail` on the User model.
- **WhatsApp provider integration** — meeting link + OTP messages are log-only locally. A provider (Fonnte/Twilio/Woot) drops into `NotificationService::sendMeetingLink` and the OTP flow without touching callers.
- ~~**Ticket management UI** — no organizer-facing ticket editor~~ — shipped: tickets are defined in the Create/Edit `EventForm` and can be reconciled through the event-scoped ticket endpoints. `TicketController` (repaired from a previously corrupt file) lives at `App\Http\Controllers` rather than `App\Http\Controllers\Web`; harmless, but a namespace move would match the other web controllers.
- **Ticket name uniqueness across events** — `ValidatesTicketFields` enforces `distinct` per payload only; there is no DB-level `unique(event_id, name)` constraint, so two tickets with the same name can coexist if created via separate requests.
- ~~**Booking flow** — no feature tests~~ — shipped: seamless guest transaction page (attendee capture + recipient contact) → signed checkout → emails + QR. `BookingPaymentTest` covers it (19 tests). Note the landing payload carries `can_book`; keep it in sync if the shape changes.
- **Guest account claim** — guest checkout creates a passwordless attendee account from the contact email; the buyer can claim it via the existing magic-link/OTP login. No explicit "claim your account" nudge is sent yet — consider adding it to `BookingPaidNotification`.
- **Signed-URL expiry** — checkout/receipt links use permanent signed URLs (`URL::signedRoute`) so a guest can pay later. Switch to `temporarySignedRoute` if links must expire.
- **Payment gateway** — `PaymentMethod` selection records the method and settles as a simulated payment. Wire a real gateway (Midtrans/Xendit) inside `BookingService::pay` + `BookingRepository::markPaid`; the controller/enum contract stays unchanged.
- **WhatsApp provider** — WhatsApp sends are quota-gated and logged (`WA_TICKETS_SENDING`). Integrate a provider (Fonnte/Twilio) inside `NotificationService::sendTicketWhatsApp` to actually push them and only then consume quota (or refund on failure); callers stay unchanged.
- **QR check-in endpoint** — QR payload is the raw `ticket_code`; there is no public scan/verify route yet. Add a signed verify route + `BookingTicket::canCheckIn()` usage when the door-scan flow is built.
- **Legacy booking controller** — `app/Http/Controllers/BookingController.php` (non-`Web` namespace) is unreferenced dead code: it calls `BookingService` methods that no longer exist (`createReservation`, `confirmBooking`, `updatePaymentStatus`) and missing `bookings.*` blade views. The live flow is `Web\BookingController` + `BookingService` + Inertia `Bookings/Pay|Show`. Safe to delete.
- **Public event discovery** — `Welcome` still renders `MOCK_EVENTS`; not yet wired to real published events.
- Public event listing (`events.index` public view) and detail pages are not wired to Inertia routes yet (the organizer-facing `events.index` My Events page exists).
- If the cache store is ever switched away from a lock-capable driver, the double-submit lock degrades silently — guard with a config check or feature test against the production cache driver.
