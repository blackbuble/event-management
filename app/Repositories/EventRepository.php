<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventRepository
{
    /**
     * Natural-key idempotency window (seconds) for duplicate submit protection.
     */
    public const DUPLICATE_WINDOW_SECONDS = 60;

    /**
     * Find an event the same organizer just created with an identical natural key
     * (title, type, schedule, venue) so double submits return the existing event
     * instead of inserting a duplicate.
     */
    public function findRecentDuplicate(int $userId, array $data): ?Event
    {
        return Event::query()
            ->where('user_id', $userId)
            ->where('title', $data['title'])
            ->where('type', $data['type'])
            ->where('start_date', Carbon::parse($data['start_date'])->format('Y-m-d H:i:s'))
            ->where('end_date', Carbon::parse($data['end_date'])->format('Y-m-d H:i:s'))
            ->where('venue_name', $data['venue_name'])
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
            ->first();
    }

    /**
     * Organizer's own events (paginated) with per-event confirmed booking count, newest first.
     */
    public function listForOrganizer(int $organizerId, int $perPage = 10): LengthAwarePaginator
    {
        return Event::query()
            ->where('user_id', $organizerId)
            ->withCount([
                'bookings' => fn (Builder $query) => $query->confirmed(),
            ])
            ->latest('start_date')
            ->paginate($perPage);
    }

    /**
     * Distinct users holding a confirmed booking on the event (channels pre-loaded).
     */
    public function confirmedAttendeesForEvent(int $eventId): Collection
    {
        return User::query()
            ->whereHas('bookings', fn (Builder $query) => $query
                ->where('event_id', $eventId)
                ->confirmed())
            ->get(['id', 'name', 'email', 'phone']);
    }

    public function updateMeetingLink(Event $event, string $meetingLink): Event
    {
        $event->update(['meeting_link' => $meetingLink]);

        return $event->fresh();
    }

    /**
     * Published events the organizer has already run (ended), newest first,
     * excluding the event currently being viewed.
     */
    public function pastPublishedForOrganizer(int $organizerId, int $excludeEventId, int $limit = 3): Collection
    {
        return Event::query()
            ->where('user_id', $organizerId)
            ->where('id', '!=', $excludeEventId)
            ->where('status', 'published')
            ->where('end_date', '<', now())
            ->orderByDesc('start_date')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'type', 'image', 'start_date', 'end_date']);
    }

    /**
     * Public lookup by slug (soft-deleted excluded automatically).
     */
    public function findBySlug(string $slug): ?Event
    {
        return Event::query()
            ->where('slug', $slug)
            ->with([
                'user:id,name',
                'tickets' => fn ($q) => $q->where('is_active', true)->orderBy('price'),
                'bookings' => fn ($q) => $q->where('status', 'confirmed'),
            ])
            ->first();
    }

    /**
     * Persist attribute updates inside a transaction.
     */
    public function update(Event $event, array $attributes): Event
    {
        return DB::transaction(function () use ($event, $attributes) {
            $event->update($attributes);

            return $event->fresh();
        });
    }

    public function changeStatus(Event $event, string $status): Event
    {
        $event->update(['status' => $status]);

        return $event->fresh();
    }

    public function softDelete(Event $event): bool
    {
        return (bool) $event->delete();
    }

    /**
     * Count-based stats for the organizer's own events
     * (active = published & upcoming, draft = still unpublished).
     */
    public function organizerCounts(int $organizerId): array
    {
        $counts = Event::query()
            ->where('user_id', $organizerId)
            ->selectRaw("count(*) as total,
                count(case when status = 'published' and start_date >= now() then 1 end) as active,
                count(case when status = 'draft' then 1 end) as draft")
            ->first();

        return [
            'total' => (int) $counts->total,
            'active' => (int) $counts->active,
            'draft' => (int) $counts->draft,
        ];
    }

    /**
     * Sold/reserved ticket totals across the organizer's events (single aggregate query).
     */
    public function organizerTicketTotals(int $organizerId): array
    {
        $totals = Event::query()
            ->where('user_id', $organizerId)
            ->join('tickets', 'tickets.event_id', '=', 'events.id')
            ->selectRaw('coalesce(sum(tickets.quantity_sold), 0) as sold,
                coalesce(sum(tickets.quantity_reserved), 0) as reserved')
            ->first();

        return [
            'sold' => (int) $totals->sold,
            'reserved' => (int) $totals->reserved,
        ];
    }

    /**
     * Create an event inside a database transaction with race-safe unique slug generation.
     */
    public function create(array $attributes): Event
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['slug'] = $this->generateUniqueSlug($attributes['title']);

            try {
                return Event::create($attributes);
            } catch (UniqueConstraintViolationException) {
                // Slug collided despite the pessimistic lock (gap-lock miss). Retry with random suffix.
                $attributes['slug'] = $this->randomSlug($attributes['title']);

                return Event::create($attributes);
            }
        });
    }

    /**
     * Slug is unique across the whole table (soft-deleted rows included),
     * so uniqueness must account for trashed events too.
     */
    protected function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));

        // Pessimistic lock serializes concurrent creators competing for the same slug prefix.
        $count = Event::query()
            ->withTrashed()
            ->where('slug', 'like', $base.'%')
            ->lockForUpdate()
            ->count();

        return $count > 0 ? "{$base}-".($count + 1) : $base;
    }

    protected function randomSlug(string $title): string
    {
        return Str::slug($title).'-'.Str::lower(Str::random(6));
    }
}
