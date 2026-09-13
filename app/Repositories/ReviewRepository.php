<?php

namespace App\Repositories;

use App\Models\EventReview;
use Illuminate\Support\Facades\DB;

class ReviewRepository
{
    /**
     * Aggregate rating across every event owned by the organizer.
     *
     * @return array{average: float|null, count: int}
     */
    public function organizerRatingSummary(int $organizerId): array
    {
        $summary = DB::table('event_reviews')
            ->join('events', 'events.id', '=', 'event_reviews.event_id')
            ->where('events.user_id', $organizerId)
            ->selectRaw('avg(event_reviews.rating) as average, count(*) as total')
            ->first();

        return [
            'average' => $summary?->average !== null ? round((float) $summary->average, 2) : null,
            'count' => (int) ($summary->total ?? 0),
        ];
    }

    public function existsForUser(int $eventId, int $userId): bool
    {
        return EventReview::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function findForUser(int $eventId, int $userId): ?EventReview
    {
        return EventReview::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $attributes): EventReview
    {
        return EventReview::create($attributes);
    }
}
