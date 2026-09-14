<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Repositories\EventAnalyticsRepository;
use Illuminate\Support\Collection;

class EventAnalyticsService
{
    public function __construct(
        private readonly EventAnalyticsRepository $analyticsRepository,
    ) {}

    /**
     * Organizer-facing analytics for a single event: KPIs, per-ticket
     * performance and a paid-sales timeline.
     *
     * @return array<string, mixed>
     */
    public function forEvent(Event $event, int $timelineDays = 14): array
    {
        $event->loadMissing('categoryModel');

        $booking = $this->analyticsRepository->bookingSummary($event->id);
        $totals = $this->analyticsRepository->ticketTotals($event->id);
        $revenueByTicket = $this->analyticsRepository->paidRevenueByTicket($event->id);
        $checkedIn = $this->analyticsRepository->checkedInCount($event->id);
        $timeline = $this->analyticsRepository->paidTimeline($event->id, $timelineDays);

        // Fall back to event capacity when tickets carry no quota.
        $quota = $totals['quota'] > 0 ? $totals['quota'] : (int) ($event->capacity ?? 0);
        $available = max(0, $totals['quota'] - $totals['sold'] - $totals['reserved']);

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status,
                'type' => $event->type,
                'category' => $event->category,
                'category_label' => $event->categoryModel?->label(app()->getLocale()) ?? $event->category,
                'city' => $event->city,
                'start_date' => $event->start_date?->toIso8601String(),
                'end_date' => $event->end_date?->toIso8601String(),
            ],
            'summary' => [
                'revenue' => $booking['revenue'],
                'tickets_sold' => $totals['sold'],
                'tickets_reserved' => $totals['reserved'],
                'tickets_available' => $available,
                'total_quota' => $quota,
                'sell_through' => $quota > 0 ? round($totals['sold'] / $quota * 100, 1) : 0.0,
                'total_bookings' => $booking['total'],
                'confirmed_bookings' => $booking['confirmed'],
                'pending_bookings' => $booking['pending'],
                'cancelled_bookings' => $booking['cancelled'],
                'checked_in' => $checkedIn,
                'capacity' => $event->capacity,
            ],
            'tickets' => $this->mapTickets($event, $revenueByTicket),
            'timeline' => $this->mapTimeline($timeline, $timelineDays),
        ];
    }

    /**
     * @param  Collection<int|string, object{revenue: float, paid_quantity: int}>  $revenueByTicket
     * @return array<int, array<string, mixed>>
     */
    private function mapTickets(Event $event, Collection $revenueByTicket): array
    {
        return $event->tickets()
            ->orderBy('price')
            ->get(['id', 'name', 'price', 'quantity', 'quantity_sold', 'quantity_reserved', 'is_active'])
            ->map(function (Ticket $ticket) use ($revenueByTicket) {
                $remaining = max(0, $ticket->quantity - $ticket->quantity_sold - $ticket->quantity_reserved);

                return [
                    'id' => $ticket->id,
                    'name' => $ticket->name,
                    'price' => (float) $ticket->price,
                    'quantity' => (int) $ticket->quantity,
                    'sold' => (int) $ticket->quantity_sold,
                    'reserved' => (int) $ticket->quantity_reserved,
                    'remaining' => $remaining,
                    'is_active' => (bool) $ticket->is_active,
                    'sell_through' => $ticket->quantity > 0
                        ? round($ticket->quantity_sold / $ticket->quantity * 100, 1)
                        : 0.0,
                    'revenue' => (float) ($revenueByTicket[$ticket->id]->revenue ?? 0),
                    'paid_quantity' => (int) ($revenueByTicket[$ticket->id]->paid_quantity ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Fill every day in the window (zeros included) so the chart has no gaps.
     *
     * @param  Collection<int, object{date: string, bookings: int, revenue: float}>  $timeline
     * @return array<int, array{date: string, bookings: int, revenue: float}>
     */
    private function mapTimeline(Collection $timeline, int $days): array
    {
        $byDate = $timeline->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row = $byDate->get($date);

            $result[] = [
                'date' => $date,
                'bookings' => (int) ($row->bookings ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        }

        return $result;
    }
}
