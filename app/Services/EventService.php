<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Repositories\EventRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventService
{
    /**
     * Lock TTL and max wait (seconds) serializing concurrent creations per organizer.
     */
    private const CREATE_LOCK_TTL = 15;

    private const CREATE_LOCK_WAIT = 5;

    /**
     * Send lock TTL and max wait (seconds) per event.
     */
    private const SEND_LOCK_TTL = 15;

    private const SEND_LOCK_WAIT = 3;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Create new event.
     *
     * Double-submit safe: per-organizer distributed lock + natural-key idempotency
     * (identical event created within the dedupe window returns the existing row).
     */
    public function createEvent(array $data, int $userId): Event
    {
        $lock = Cache::lock('event-create:'.$userId, self::CREATE_LOCK_TTL);

        return $lock->block(self::CREATE_LOCK_WAIT, function () use ($data, $userId) {
            $data['user_id'] = $userId;
            $data = $this->applyOnlineVenueDefaults($data);

            $duplicate = $this->eventRepository->findRecentDuplicate($userId, $data);

            if ($duplicate !== null) {
                return $duplicate;
            }

            // Filesystem operation (not transactional) — only after the dedupe check
            // so a duplicate submit never stores an orphaned banner file.
            if (isset($data['image']) && $data['image']) {
                $data['image'] = $data['image']->store('events', 'public');
            }

            // Persistence + slug uniqueness handled transactionally by the repository
            return $this->eventRepository->create($data);
        });
    }

    /**
     * Set or update the meeting link of an online/hybrid event
     * (organizer may have deferred it at creation time).
     */
    public function updateMeetingLink(Event $event, string $meetingLink): Event
    {
        return $this->eventRepository->updateMeetingLink($event, $meetingLink);
    }

    public function findEventBySlug(string $slug): ?Event
    {
        return $this->eventRepository->findBySlug($slug);
    }

    /**
     * Organizer's own events as lean arrays + pagination meta for the Inertia list.
     *
     * @return array{events: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listEventsForOrganizer(int $organizerId, int $perPage = 10): array
    {
        $paginator = $this->eventRepository->listForOrganizer($organizerId, $perPage);

        return [
            'events' => $paginator->getCollection()->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'type' => $event->type,
                'status' => $event->status,
                'start_date' => $event->start_date?->toIso8601String(),
                'end_date' => $event->end_date?->toIso8601String(),
                'meeting_link' => $event->meeting_link,
                'confirmed_bookings' => (int) $event->bookings_count,
            ])->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Broadcast the meeting link to every confirmed attendee
     * via each channel they have on file (email / WhatsApp).
     *
     * Serialized per event with a distributed lock so a double click cannot
     * queue duplicate notifications; re-sending after the lock releases is
     * still an explicit organizer action.
     *
     * @return array{emailed: int, whatsapped: int}
     */
    public function sendMeetingLinkToAttendees(Event $event): array
    {
        $lock = Cache::lock('event-link-send:'.$event->id, self::SEND_LOCK_TTL);

        return $lock->block(self::SEND_LOCK_WAIT, function () use ($event) {
            $attendees = $this->eventRepository->confirmedAttendeesForEvent($event->id);

            $counts = ['emailed' => 0, 'whatsapped' => 0];

            foreach ($attendees as $attendee) {
                $channels = $this->notificationService->sendMeetingLink($attendee, $event);

                if ($channels['emailed']) {
                    $counts['emailed']++;
                }

                if ($channels['whatsapped']) {
                    $counts['whatsapped']++;
                }
            }

            return $counts;
        });
    }

    /**
     * Online events have no physical venue; schema requires venue_name/venue_address
     * so fall back to sensible defaults instead of forcing organizers to type them.
     */
    protected function applyOnlineVenueDefaults(array $data): array
    {
        if (($data['type'] ?? null) === 'online') {
            $data['venue_name'] = $data['venue_name'] ?? 'Online Event';
            $data['venue_address'] = $data['venue_address'] ?? ($data['meeting_link'] ?? 'Online');
        }

        return $data;
    }

    /**
     * Update an existing event (image replacement handled here,
     * persistence delegated to the repository).
     */
    public function updateEvent(Event $event, array $data): Event
    {
        if (isset($data['image']) && $data['image']) {
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }

            $data['image'] = $data['image']->store('events', 'public');
        } else {
            // Keep the existing banner when no replacement was uploaded
            unset($data['image']);
        }

        return $this->eventRepository->update($event, $data);
    }

    /**
     * Publish a draft event.
     */
    public function publishEvent(Event $event): Event
    {
        if ($event->status !== 'draft') {
            throw new \InvalidArgumentException('Only draft events can be published.');
        }

        return $this->eventRepository->changeStatus($event, 'published');
    }

    /**
     * Cancel a draft or published event.
     */
    public function cancelEvent(Event $event): Event
    {
        if ($event->status === 'cancelled') {
            throw new \InvalidArgumentException('Event is already cancelled.');
        }

        return $this->eventRepository->changeStatus($event, 'cancelled');
    }

    /**
     * Soft-delete an event. Events with active bookings cannot be deleted
     * (business guard), persistence delegated to the repository.
     */
    public function deleteEvent(Event $event): bool
    {
        if ($event->bookings()->where('status', '!=', 'cancelled')->exists()) {
            throw new \InvalidArgumentException('Cannot delete event with active bookings.');
        }

        if ($event->image) {
            Storage::disk('public')->delete($event->image);
        }

        return $this->eventRepository->softDelete($event);
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
            if ($ticket->bookingTickets()->whereHas('booking', function ($q) {
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
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        // Type filter
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Date range
        if (! empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('start_date', '<=', $filters['date_to']);
        }

        // Price filter (free/paid)
        if (isset($filters['is_free'])) {
            if ($filters['is_free']) {
                $query->whereHas('tickets', function ($q) {
                    $q->where('price', 0);
                });
            } else {
                $query->whereHas('tickets', function ($q) {
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
            'tickets_available' => $event->tickets->sum(function ($ticket) {
                return $ticket->remainingQuantity();
            }),
            'total_bookings' => $event->bookings()->count(),
            'confirmed_bookings' => $event->bookings()->confirmed()->count(),
            'total_revenue' => $event->totalRevenue(),
            'checked_in' => $event->bookings()
                ->confirmed()
                ->whereHas('bookingTickets', function ($q) {
                    $q->where('checked_in', true);
                })
                ->count(),
        ];
    }
}
