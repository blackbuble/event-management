# Event Management - Create Event Feature

## Feature Overview

Organizers and admins can create events from the dashboard (`/dashboard`) via a dedicated form page (`/dashboard/events/create`). The form captures every column of the `events` table (see schema below). Creation supports three event types (offline / online / hybrid), draft or publish-on-create status, optional banner upload, and optional geo-coordinates.

The `tickets` table is intentionally out of scope for this step; tickets are managed per-event after creation (`EventService::createTicket`).

### `events` table → form field mapping

| Column | Form field | Validation |
|---|---|---|
| `title` | Event name | required, string, max 255 |
| `slug` | *(generated)* | race-safe unique (see below) |
| `description` | Description textarea | required, string, max 5000 |
| `image` | Banner file input | nullable, image, max 2048 KB |
| `type` | Type card selector | required, `online`/`offline`/`hybrid` |
| `venue_name` | Venue name | required unless `type=online` (default `Online Event`) |
| `venue_address` | Full address textarea | required unless `type=online` (defaults to meeting link) |
| `latitude` / `longitude` | Collapsible location pin | nullable, numeric, bounded ±90 / ±180 |
| `meeting_link` | Meeting link (shown for online/hybrid) | optional — `nullable, url, max 255`; can be deferred and added later from My Events (see below) |
| `start_date` / `end_date` | datetime-local inputs | required, `start > now`, `end > start` |
| `capacity` | Capacity number input | nullable, integer, min 1 |
| `status` | Draft / Publish selector | required, `draft`/`published` (`cancelled` not allowed at creation) |
| `user_id` | *(from session)* | owner = authenticated organizer |

## Architecture Blueprint

```
GET  /dashboard/events/create   → Web\EventController@create
                                  → Gate::authorize('create', Event) [EventPolicy: admin|organizer]
                                  → Inertia::render('Events/Create')

POST /dashboard/events          → Web\EventController@store (thin) [throttle:10,1]
                                  → StoreEventRequest (validation + policy authorization)
                                  → EventService::createEvent(validated, userId)
                                      → Cache::lock('event-create:{userId}', 15s).block(5s)
                                          → applyOnlineVenueDefaults()
                                          → EventRepository::findRecentDuplicate()  ← natural-key idempotency (60s window)
                                              → duplicate found → return existing event (no image store)
                                          → image store on public disk
                                          → EventRepository::create()  ← DB::transaction
                                                → generateUniqueSlug (lockForUpdate)
                                                → Event::create (+ UniqueConstraintViolation retry)
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

```
GET    /dashboard/events/{event}/edit        → events.edit (policy: update) → prefilled shared EventForm
PATCH  /dashboard/events/{event}             → events.update [throttle:10,1] UpdateEventRequest (policy: update)
                                                slug is NOT editable (never in validated payload)
PATCH  /dashboard/events/{event}/publish     → events.publish [throttle:10,1] guard: draft only
PATCH  /dashboard/events/{event}/cancel      → events.cancel [throttle:10,1] guard: not already cancelled
DELETE /dashboard/events/{event}             → events.destroy [throttle:10,1] guard: no active bookings
```

- **Shared form:** `resources/js/Components/EventForm.tsx` powers both Create and Edit (DRY) — edit pre-fills values, shows the current banner with a replace affordance, and keeps the meeting-link "Add Later" option.
- **Status transitions:** explicit `publishEvent`/`cancelEvent` service methods enforce valid transitions (`draft→published`, `draft|published→cancelled`); invalid attempts surface as localized flash errors, never silent no-ops.
- **Delete guard:** soft-delete blocked while non-cancelled bookings exist (business rule in the service, typed `InvalidArgumentException`).
- **Repository discipline:** update/persist via `EventRepository::update`/`softDelete` — the service handles only image filesystem side effects and business guards.

### Events/Show props contract

```ts
event: {
    id: number; title: string; slug: string; description: string | null;
    type: 'online' | 'offline' | 'hybrid'; status: 'draft' | 'published' | 'cancelled';
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
- **Data integrity:** creation runs in `DB::transaction` owned by the repository; service stays side-effect-free for unit testing.
- **Input hygiene:** empty optional inputs are normalized to `null` in `prepareForValidation()`; image uploads restricted to image MIME types and 2MB.
- **Lifecycle authorization:** edit/update/publish/cancel/delete all enforce `EventPolicy::update`/`delete` (owner or admin) — verified by feature tests covering non-owner organizer and attendee 403s on every endpoint.
- **Slug immutability:** `slug` is never part of `UpdateEventRequest::validated()`, so a crafted payload cannot hijack or alter an event URL (covered by test).
- **Status transition guards:** publish only from `draft`, cancel only when not already cancelled — invalid transitions return a localized flash error instead of silently mutating state.
- **Delete guard:** soft-delete is blocked while non-cancelled bookings exist; the event (and its banner) remain intact on rejection.
- **Landing visibility:** draft/cancelled event pages 404 for everyone except the owner/admin — no accidental leak of unpublished content.
- **Review eligibility:** only confirmed attendees of a finished (non-cancelled) event may review, once; owners cannot review their own event. Enforced in `ReviewService` (not just UI).
- **Attendee privacy:** the public payload exposes attendee **initials only** (plus optional avatar image), never full names; counts include confirmed bookings only.

## Testing

- Feature: `tests/Feature/EventCreationTest.php` (21 tests) — guest redirect, 403 for attendees (page + POST), profile-incomplete redirect, Inertia page render, draft/online/published creation, banner storage, full validation matrix, deferred meeting link (online/hybrid without link), duplicate slug handling, double-submit idempotency, rate limiting.
- Feature: `tests/Feature/MeetingLinkTest.php` (16 tests) — My Events page render + ownership isolation + pagination, set link (owner/non-owner/attendee/invalid URL/offline-type guard), send guards (link unset/cancelled/ended), delivery to confirmed attendees only (pending bookings and other-event attendees excluded), per-user dedupe, send + update throttle 429.
- Feature: `tests/Feature/EventManagementTest.php` (18 tests) — public landing visibility (published/draft/cancelled/unknown slug), landing payload contract (slug, coordinates, organizer, tickets), edit page authorization, update (slug immutability, banner replace deletes old file, banner kept without upload, validation), publish/cancel transitions + guards, delete with/without active bookings, guest redirects on every lifecycle endpoint.
- Feature: `tests/Feature/EventReviewTest.php` (13 tests) — guest block, eligibility matrix (confirmed booking required, ended only, cancelled excluded, owner excluded, duplicate rejected), rating bounds 1–5, landing `can_review`/`my_review`, organizer rating aggregate, past-events exclusion of current/future, registered count + initials-only avatars, app-timezone ISO offset.
- Unit: `tests/Unit/EventServiceTest.php` (12 tests) — repository delegation, duplicate short-circuit, online venue defaults, venue passthrough, image path contract, meeting link delegation, per-user notify + channel counts, lean list mapping, update delegation (image/no-image), banner replacement, publish/cancel transition guards.
- Unit: `tests/Unit/NotificationServiceTest.php` (3 tests) — queued email for email users, WhatsApp log payload, no-op for channel-less users.
- Full QA & security audit report with test evidence, vulnerability fix history, and outstanding items: [`docs/qa/EVENT_CREATION_QA.md`](../qa/EVENT_CREATION_QA.md).

## Known Follow-ups (Tech Debt)

- ~~Legacy `app/Http/Controllers/EventController.php` dead code~~ — removed.
- ~~`verified` middleware inert~~ — removed from `dashboard` and event routes. `User` does not implement `MustVerifyEmail` (unified OTP/magic-link auth), so the middleware could never fire; keeping it was misleading dead config. Re-add only together with `MustVerifyEmail` on the User model.
- **WhatsApp provider integration** — meeting link + OTP messages are log-only locally. A provider (Fonnte/Twilio/Woot) drops into `NotificationService::sendMeetingLink` and the OTP flow without touching callers.
- **Ticket management UI** — the ticket schema + `EventService` ticket CRUD exist, but no organizer-facing ticket editor yet; the landing page renders available tickets (remaining/min/max per order).
- **Booking flow** — backend exists (`Web\BookingController`, `BookingService`, `POST /events/{event}/book` with `auth` + `throttle:10,1`) and the landing page exposes a booking CTA; it has no feature tests yet and is owned outside the event module — coordinate before changing `EventService::getPublicEventData`, since booking relies on the same payload.
- **Legacy booking controller** — `app/Http/Controllers/BookingController.php` (non-`Web` namespace) is unreferenced dead code; the live endpoint uses `Web\BookingController`. Safe to delete after confirming no in-flight work depends on it.
- **Public event discovery** — `Welcome` still renders `MOCK_EVENTS`; not yet wired to real published events.
- Public event listing (`events.index` public view) and detail pages are not wired to Inertia routes yet (the organizer-facing `events.index` My Events page exists).
- If the cache store is ever switched away from a lock-capable driver, the double-submit lock degrades silently — guard with a config check or feature test against the production cache driver.
