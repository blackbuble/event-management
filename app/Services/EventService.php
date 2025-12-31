<?php
namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventService
{
    /**
     * Create new event
     */
    public function createEvent(array $data, int $userId): Event
    {
        return DB::transaction(function () use ($data, $userId) {
            // Handle image upload
            if (isset($data['image']) && $data['image']) {
                $data['image'] = $data['image']->store('events', 'public');
            }

            $data['user_id'] = $userId;
            
            $event = Event::create($data);

            return $event;
        });
    }

    /**
     * Update event
     */
    public function updateEvent(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            // Handle image upload
            if (isset($data['image']) && $data['image']) {
                // Delete old image
                if ($event->image) {
                    Storage::disk('public')->delete($event->image);
                }
                $data['image'] = $data['image']->store('events', 'public');
            }

            $event->update($data);
            
            return $event->fresh();
        });
    }

    /**
     * Delete event
     */
    public function deleteEvent(Event $event): bool
    {
        return DB::transaction(function () use ($event) {
            // Check if event has bookings
            if ($event->bookings()->where('status', '!=', 'cancelled')->exists()) {
                throw new \Exception('Cannot delete event with active bookings');
            }

            // Delete image
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }

            return $event->delete();
        });
    }

    /**
     * Create ticket for event
     */
    public function createTicket(Event $event, array $ticketData): Ticket
    {
        return DB::transaction(function () use ($event, $ticketData) {
            $ticketData['event_id'] = $event->id;
            
            return Ticket::create($ticketData);
        });
    }

    /**
     * Update ticket
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $data) {
            // Prevent reducing quantity below sold amount
            if (isset($data['quantity'])) {
                $totalUsed = $ticket->quantity_sold + $ticket->quantity_reserved;
                if ($data['quantity'] < $totalUsed) {
                    throw new \InvalidArgumentException(
                        "Cannot reduce quantity below {$totalUsed} (sold: {$ticket->quantity_sold}, reserved: {$ticket->quantity_reserved})"
                    );
                }
            }

            $ticket->update($data);
            
            return $ticket->fresh();
        });
    }

    /**
     * Delete ticket
     */
    public function deleteTicket(Ticket $ticket): bool
    {
        return DB::transaction(function () use ($ticket) {
            // Check if ticket has bookings
            if ($ticket->bookingTickets()->whereHas('booking', function($q) {
                $q->where('status', '!=', 'cancelled');
            })->exists()) {
                throw new \Exception('Cannot delete ticket with active bookings');
            }

            return $ticket->delete();
        });
    }

    /**
     * Get available events with filters
     */
    public function getAvailableEvents(array $filters = [])
    {
        $query = Event::with(['user', 'tickets'])
            ->published()
            ->upcoming();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        // Type filter
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('start_date', '<=', $filters['date_to']);
        }

        // Price filter (free/paid)
        if (isset($filters['is_free'])) {
            if ($filters['is_free']) {
                $query->whereHas('tickets', function($q) {
                    $q->where('price', 0);
                });
            } else {
                $query->whereHas('tickets', function($q) {
                    $q->where('price', '>', 0);
                });
            }
        }

        return $query->orderBy('start_date')->paginate(12);
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(Event $event): array
    {
        return [
            'total_tickets' => $event->tickets->sum('quantity'),
            'tickets_sold' => $event->tickets->sum('quantity_sold'),
            'tickets_reserved' => $event->tickets->sum('quantity_reserved'),
            'tickets_available' => $event->tickets->sum(function($ticket) {
                return $ticket->remainingQuantity();
            }),
            'total_bookings' => $event->bookings()->count(),
            'confirmed_bookings' => $event->bookings()->confirmed()->count(),
            'total_revenue' => $event->totalRevenue(),
            'checked_in' => $event->bookings()
                ->confirmed()
                ->whereHas('bookingTickets', function($q) {
                    $q->where('checked_in', true);
                })
                ->count(),
        ];
    }
}