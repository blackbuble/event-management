<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\EventRepository;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BookingRepository $bookingRepository,
    ) {}

    /**
     * Role-aware dashboard payload: organizers get sales stats for their events,
     * attendees get personal booking stats.
     */
    public function getDashboardData(User $user): array
    {
        return $user->hasRole(['admin', 'organizer'])
            ? $this->organizerDashboard($user)
            : $this->attendeeDashboard($user);
    }

    protected function organizerDashboard(User $user): array
    {
        $eventCounts = $this->eventRepository->organizerCounts($user->id);
        $ticketTotals = $this->eventRepository->organizerTicketTotals($user->id);

        return [
            'view' => 'organizer',
            'stats' => [
                'active_events' => $eventCounts['active'],
                'draft_events' => $eventCounts['draft'],
                'tickets_sold' => $ticketTotals['sold'],
                'tickets_reserved' => $ticketTotals['reserved'],
                'revenue' => $this->bookingRepository->paidRevenueForOrganizer($user->id),
                'confirmed_bookings' => $this->bookingRepository->confirmedCountForOrganizer($user->id),
            ],
            'recent_activity' => $this->mapOrganizerActivity(
                $this->bookingRepository->recentForOrganizer($user->id)
            ),
        ];
    }

    protected function attendeeDashboard(User $user): array
    {
        return [
            'view' => 'attendee',
            'stats' => [
                'upcoming_bookings' => $this->bookingRepository->upcomingCountForAttendee($user->id),
                'total_tickets' => $this->bookingRepository->ticketCountForAttendee($user->id),
                'total_spent' => $this->bookingRepository->totalSpentForAttendee($user->id),
            ],
            'recent_activity' => $this->mapAttendeeActivity(
                $this->bookingRepository->recentForAttendee($user->id)
            ),
        ];
    }

    /**
     * Lean activity arrays for the frontend — never raw models/collections.
     */
    protected function mapOrganizerActivity(Collection $bookings): array
    {
        return $bookings->map(fn (Booking $booking) => [
            'event_title' => $booking->event?->title,
            'event_slug' => $booking->event?->slug,
            'attendee_name' => $booking->user?->name,
            'status' => $booking->status,
            'total_amount' => (float) $booking->total_amount,
            'created_at' => $booking->created_at?->toIso8601String(),
        ])->all();
    }

    protected function mapAttendeeActivity(Collection $bookings): array
    {
        return $bookings->map(fn (Booking $booking) => [
            'event_title' => $booking->event?->title,
            'event_slug' => $booking->event?->slug,
            'status' => $booking->status,
            'total_amount' => (float) $booking->total_amount,
            'created_at' => $booking->created_at?->toIso8601String(),
        ])->all();
    }
}
