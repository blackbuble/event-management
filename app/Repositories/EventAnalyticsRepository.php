<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Models\BookingTicket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventAnalyticsRepository
{
    /**
     * Booking counts by status + paid revenue, in a single aggregate query.
     *
     * @return array{total: int, confirmed: int, pending: int, cancelled: int, revenue: float}
     */
    public function bookingSummary(int $eventId): array
    {
        $row = Booking::query()
            ->where('event_id', $eventId)
            ->selectRaw("count(*) as total,
                count(case when status = 'confirmed' then 1 end) as confirmed,
                count(case when status = 'pending' then 1 end) as pending,
                count(case when status = 'cancelled' then 1 end) as cancelled,
                coalesce(sum(case when payment_status = 'paid' then total_amount else 0 end), 0) as revenue")
            ->first();

        return [
            'total' => (int) $row->total,
            'confirmed' => (int) $row->confirmed,
            'pending' => (int) $row->pending,
            'cancelled' => (int) $row->cancelled,
            'revenue' => (float) $row->revenue,
        ];
    }

    /**
     * @return array{quota: int, sold: int, reserved: int}
     */
    public function ticketTotals(int $eventId): array
    {
        $row = DB::table('tickets')
            ->where('event_id', $eventId)
            ->selectRaw('coalesce(sum(quantity), 0) as quota,
                coalesce(sum(quantity_sold), 0) as sold,
                coalesce(sum(quantity_reserved), 0) as reserved')
            ->first();

        return [
            'quota' => (int) $row->quota,
            'sold' => (int) $row->sold,
            'reserved' => (int) $row->reserved,
        ];
    }

    /**
     * Paid revenue + paid quantity grouped by ticket type.
     *
     * @return Collection<int|string, object{revenue: float, paid_quantity: int}>
     */
    public function paidRevenueByTicket(int $eventId): Collection
    {
        return BookingTicket::query()
            ->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
            ->where('bookings.event_id', $eventId)
            ->where('bookings.payment_status', 'paid')
            ->where('bookings.status', '!=', 'cancelled')
            ->groupBy('booking_tickets.ticket_id')
            ->selectRaw('booking_tickets.ticket_id,
                coalesce(sum(booking_tickets.price * booking_tickets.quantity), 0) as revenue,
                coalesce(sum(booking_tickets.quantity), 0) as paid_quantity')
            ->get()
            ->keyBy('ticket_id');
    }

    /**
     * Distinct checked-in attendees for the event (non-cancelled bookings only).
     */
    public function checkedInCount(int $eventId): int
    {
        return (int) BookingTicket::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('event_id', $eventId)
                ->where('status', '!=', 'cancelled'))
            ->where('checked_in', true)
            ->sum('quantity');
    }

    /**
     * Paid bookings per day since the given date (inclusive), for the trend chart.
     *
     * @return Collection<int, object{date: string, bookings: int, revenue: float}>
     */
    public function paidTimeline(int $eventId, int $days = 14): Collection
    {
        return Booking::query()
            ->where('event_id', $eventId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as date, count(*) as bookings, coalesce(sum(total_amount), 0) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
