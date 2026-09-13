<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreReviewRequest;
use App\Models\Event;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Submit a review for an attended event (eligibility enforced in the service).
     */
    public function store(StoreReviewRequest $request, Event $event): RedirectResponse
    {
        try {
            $this->reviewService->submit(
                $event,
                $request->user(),
                (int) $request->validated('rating'),
                $request->validated('comment'),
            );
        } catch (\InvalidArgumentException) {
            $error = app()->getLocale() === 'id'
                ? 'Anda belum berhak menilai event ini (hanya peserta dengan booking terkonfirmasi pada event yang sudah selesai, satu kali).'
                : 'You are not eligible to review this event (confirmed attendees of a finished event only, one review each).';

            return back()->with('error', $error);
        }

        $message = app()->getLocale() === 'id'
            ? 'Terima kasih! Penilaian Anda sudah tersimpan.'
            : 'Thank you! Your review has been saved.';

        return back()->with('message', $message);
    }
}
