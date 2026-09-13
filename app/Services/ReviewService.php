<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventReview;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\ReviewRepository;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly BookingRepository $bookingRepository,
    ) {}

    /**
     * A user may review an event they attended: event ended, not cancelled,
     * confirmed booking, and no review submitted yet. Owners review themselves out.
     */
    public function canReview(Event $event, ?User $user): bool
    {
        if ($user === null || $event->user_id === $user->id) {
            return false;
        }

        if (! $event->end_date?->isPast() || $event->status === 'cancelled') {
            return false;
        }

        return $this->bookingRepository->hasConfirmedBooking($event->id, $user->id)
            && ! $this->reviewRepository->existsForUser($event->id, $user->id);
    }

    /**
     * Submit a review, enforcing eligibility.
     *
     * @throws \InvalidArgumentException when the user is not eligible
     */
    public function submit(Event $event, User $user, int $rating, ?string $comment = null): EventReview
    {
        if (! $this->canReview($event, $user)) {
            throw new \InvalidArgumentException('Not eligible to review this event.');
        }

        return $this->reviewRepository->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function myReview(Event $event, ?User $user): ?EventReview
    {
        if ($user === null) {
            return null;
        }

        return $this->reviewRepository->findForUser($event->id, $user->id);
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function organizerRating(int $organizerId): array
    {
        return $this->reviewRepository->organizerRatingSummary($organizerId);
    }
}
