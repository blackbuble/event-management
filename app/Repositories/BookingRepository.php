<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingRepository
{
    /**
     * Number of distinct attendees with a confirmed booking on the event.
     */
    public function confirmedAttendeeCountForEvent(int $eventId): int
    {
        return (int) Booking::query()
            ->where('event_id', $eventId)
            ->confirmed()
            ->distinct()
            ->count('user_id');
    }

    /**
     * Distinct attendees (earliest registration first) for the avatar stack,
     * with both avatar channels for URL resolution.
     */
    public function confirmedAttendeePreviewForEvent(int $eventId, int $limit = 6): Collection
    {
        return DB::table('bookings')
            ->join('users', 'users.id', '=', 'bookings.user_id')
            ->where('bookings.event_id', $eventId)
            ->where('bookings.status', 'confirmed')
            ->groupBy('users.id', 'users.name', 'users.avatar', 'users.social_avatar')
            ->orderByRaw('min(bookings.created_at) asc')
            ->limit($limit)
            ->get(['users.id', 'users.name', 'users.avatar', 'users.social_avatar']);
    }

    /**
     * Whether the user holds a confirmed booking on the event.
     */
    public function hasConfirmedBooking(int $eventId, int $userId): bool
    {
        return Booking::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->confirmed()
            ->exists();
    }

    /**
     * Latest bookings on events owned by the given organizer (with event + attendee loaded).
     */
    public function recentForOrganizer(int $organizerId, int $limit = 5): Collection
    {
        return Booking::query()
            ->whereHas('event', fn (Builder $query) => $query->where('user_id', $organizerId))
            ->with(['event:id,title,slug', 'user:id,name'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Sum of paid booking amounts across the organizer's events.
     */
    public function paidRevenueForOrganizer(int $organizerId): float
    {
        return (float) Booking::query()
            ->whereHas('event', fn (Builder $query) => $query->where('user_id', $organizerId))
            ->where('payment_status', 'paid')
            ->sum('total_amount');
    }

    /**
     * Count of confirmed bookings across the organizer's events.
     */
    public function confirmedCountForOrganizer(int $organizerId): int
    {
        return Booking::query()
            ->whereHas('event', fn (Builder $query) => $query->where('user_id', $organizerId))
            ->confirmed()
            ->count();
    }

    /**
     * Latest bookings made by the given attendee (with event loaded).
     */
    public function recentForAttendee(int $userId, int $limit = 5): Collection
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->with(['event:id,title,slug'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Upcoming confirmed booking count for the attendee.
     */
    public function upcomingCountForAttendee(int $userId): int
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->confirmed()
            ->whereHas('event', fn (Builder $query) => $query->where('start_date', '>=', now()))
            ->count();
    }

    /**
     * Total ticket quantity the attendee holds across all their bookings.
     */
    public function ticketCountForAttendee(int $userId): int
    {
        return (int) DB::table('booking_tickets')
            ->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
            ->where('bookings.user_id', $userId)
            ->where('bookings.status', '!=', 'cancelled')
            ->sum('booking_tickets.quantity');
    }

    /**
     * Sum of amounts the attendee actually paid.
     */
    public function totalSpentForAttendee(int $userId): float
    {
        return (float) Booking::query()
            ->where('user_id', $userId)
            ->where('payment_status', 'paid')
            ->sum('total_amount');
    }
}
