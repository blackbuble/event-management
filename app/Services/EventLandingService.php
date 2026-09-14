<?php

namespace App\Services;

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\EventReview;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\EventRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventLandingService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Full lean payload for the public event landing page, viewer-aware
     * (review eligibility + own review).
     */
    public function getEventPageData(Event $event, ?User $viewer = null): array
    {
        $event->loadMissing('user:id,name');

        $organizer = null;

        if ($event->user) {
            $organizer = [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'rating' => $this->reviewService->organizerRating($event->user->id),
                'past_events' => $this->mapPastEvents(
                    $this->eventRepository->pastPublishedForOrganizer($event->user->id, $event->id)
                ),
            ];
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'type' => $event->type,
            'category' => $event->category,
            'category_label' => EventCategory::tryFrom((string) $event->category)?->label(app()->getLocale())
                ?? EventCategory::Other->label(app()->getLocale()),
            'status' => $event->status,
            'image_url' => $event->image ? Storage::disk('public')->url($event->image) : null,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'meeting_link' => $event->meeting_link,
            'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
            'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
            'organizer' => $organizer,
            'start_date' => $event->start_date?->toIso8601String(),
            'end_date' => $event->end_date?->toIso8601String(),
            'capacity' => $event->capacity,
            'is_full' => $event->isFull(),
            'attendees' => [
                'count' => $this->bookingRepository->confirmedAttendeeCountForEvent($event->id),
                'avatars' => $this->mapAttendeeAvatars($event->id),
            ],
            'tickets' => $this->mapTickets($event),
            'can_book' => $viewer !== null && (int) $viewer->id !== (int) $event->user_id,
            'can_review' => $this->reviewService->canReview($event, $viewer),
            'my_review' => $this->mapReview($this->reviewService->myReview($event, $viewer)),
        ];
    }

    protected function mapTickets(Event $event): array
    {
        return $event->tickets()
            ->where('is_active', true)
            ->orderBy('price')
            ->get(['id', 'name', 'description', 'price', 'quantity', 'quantity_sold', 'quantity_reserved', 'min_per_order', 'max_per_order'])
            ->map(fn ($ticket) => [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'description' => $ticket->description,
                'price' => (float) $ticket->price,
                'remaining' => max(0, $ticket->quantity - $ticket->quantity_sold - $ticket->quantity_reserved),
                'min_per_order' => $ticket->min_per_order,
                'max_per_order' => $ticket->max_per_order,
            ])
            ->all();
    }

    /**
     * Public avatar stack exposes initials only (never the full attendee name).
     */
    protected function mapAttendeeAvatars(int $eventId): array
    {
        return $this->bookingRepository->confirmedAttendeePreviewForEvent($eventId)
            ->map(fn ($attendee) => [
                'initials' => $this->initials($attendee->name),
                'avatar_url' => $this->avatarUrl($attendee->avatar, $attendee->social_avatar),
            ])
            ->all();
    }

    protected function mapPastEvents($events): array
    {
        return $events->map(fn (Event $past) => [
            'id' => $past->id,
            'title' => $past->title,
            'slug' => $past->slug,
            'type' => $past->type,
            'image_url' => $past->image ? Storage::disk('public')->url($past->image) : null,
            'start_date' => $past->start_date?->toIso8601String(),
        ])->all();
    }

    protected function mapReview(?EventReview $review): ?array
    {
        if (! $review instanceof EventReview) {
            return null;
        }

        return [
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    protected function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];

        $letters = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : '?';
    }

    protected function avatarUrl(?string $avatar, ?string $socialAvatar): ?string
    {
        if ($socialAvatar) {
            return $socialAvatar;
        }

        if (! $avatar) {
            return null;
        }

        return filter_var($avatar, FILTER_VALIDATE_URL)
            ? $avatar
            : Storage::disk('public')->url($avatar);
    }
}
