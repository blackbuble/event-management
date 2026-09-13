# Dashboard — Real-Time Stats (Role-Aware)

## Feature Overview

The dashboard (`/dashboard`) renders **real data only** — no static placeholders. Content branches on role:

- **Organizer / Admin:** sales stats for their own events (active/draft events, tickets sold & reserved, paid revenue, confirmed bookings) + latest bookings on their events.
- **Attendee (default):** personal stats (upcoming confirmed bookings, ticket count, total spent) + their recent bookings.

The "Create Event" button is rendered only when `canCreateEvent` is true (backend `Gate::allows('create', Event::class)` → admin/organizer).

## Architecture Blueprint

```
GET /dashboard → Web\DashboardController@index [auth, profile.complete]
                 → DashboardService::getDashboardData(user)      ← role branch (Spatie)
                     organizer: EventRepository::organizerCounts / organizerTicketTotals
                                BookingRepository::paidRevenueForOrganizer / confirmedCountForOrganizer / recentForOrganizer
                     attendee:  BookingRepository::upcomingCountForAttendee / ticketCountForAttendee / totalSpentForAttendee / recentForAttendee
                 → Inertia::render('Dashboard', { dashboard, canCreateEvent })
```

- **Controller** (`app/Http/Controllers/Web/Controller.php`): `app/Http/Controllers/Web/DashboardController.php` — thin, no queries.
- **Service** (`app/Services/DashboardService.php`): role branching + mapping to lean activity arrays (`event_title`, `attendee_name`, `status`, `total_amount`, ISO `created_at`). Raw models/collections never reach the frontend.
- **Repositories:** `EventRepository` (event-centric aggregates), `BookingRepository` (booking-centric aggregates + activity feeds). Eager loading via `with(['event:id,title,slug', 'user:id,name'])` — no N+1.

## Inertia Props Contract

```ts
canCreateEvent: boolean;
dashboard: {
    view: 'organizer' | 'attendee';
    stats: {
        // organizer
        active_events: number;      // published & start_date >= now
        draft_events: number;
        tickets_sold: number;       // sum(tickets.quantity_sold) on own events
        tickets_reserved: number;
        revenue: number;            // sum(bookings.total_amount) where payment_status = paid
        confirmed_bookings: number;
        // attendee
        upcoming_bookings: number;  // confirmed & event upcoming
        total_tickets: number;      // sum(booking_tickets.quantity), cancelled excluded
        total_spent: number;        // sum(total_amount) where paid
    };
    recent_activity: Array<{
        event_title: string;
        event_slug: string;
        attendee_name?: string;     // organizer view only
        status: 'pending' | 'confirmed' | 'cancelled' | 'refunded';
        total_amount: number;
        created_at: string;         // ISO 8601
    }>;
}
```

Frontend formatting: IDR currency via `Intl.NumberFormat`, relative time ("2h ago") computed client-side from `created_at`. UI copy lives in `resources/js/config/dashboard-texts.ts` (id/en).

## Security & Resiliency Protocols

- **Authorization:** stats strictly scoped to the requesting user (`user_id` filters in every repository query — cross-organizer data leakage covered by tests). `canCreateEvent` decided server-side via `EventPolicy::create`, never inferred client-side from role names alone.
- **Data minimization:** activity items are lean mapped arrays with explicit column selection (`event:id,title,slug`, `user:id,name`) — no model dumps, no PII beyond display name.
- **Performance:** organizer counts/ticket totals use single aggregate queries (`selectRaw` with conditional counts, join + sum); activity is limited (`LIMIT 5`) and eager loaded.

## Testing

- Feature: `tests/Feature/DashboardTest.php` (6 tests) — guest redirect, zeroed empty state, per-organizer data isolation (other organizer's events excluded), past events not counted active, attendee stats & `canCreateEvent: false`, cancelled bookings excluded from attendee aggregates.
- Unit: `tests/Unit/DashboardServiceTest.php` (2 tests) — role branching and lean activity mapping with mocked repositories (no DB).
- Live data note: dev account (id 1) has been assigned the `organizer` role (kept `attendee`) so the Create Event flow is usable locally.

## Related

- Feature doc: [`EVENT_MANAGEMENT.md`](./EVENT_MANAGEMENT.md)
- QA report (create event): [`../qa/EVENT_CREATION_QA.md`](../qa/EVENT_CREATION_QA.md)
