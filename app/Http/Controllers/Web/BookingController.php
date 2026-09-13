<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreBookingRequest;
use App\Models\Event;
use App\Services\BookingService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function store(StoreBookingRequest $request, Event $event)
    {
        if ($event->status !== 'published') {
            abort(403);
        }

        try {
            $booking = $this->bookingService->createBooking(
                $event,
                $request->user()->id,
                $request->validated('tickets'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('events.show', $event->slug)
                ->with('error', $e->getMessage());
        } catch (LockTimeoutException) {
            $error = app()->getLocale() === 'id'
                ? 'Permintaan sebelumnya masih diproses. Mohon tunggu sebentar dan coba lagi.'
                : 'A previous request is still being processed. Please wait a moment and try again.';

            return redirect()->route('events.show', $event->slug)
                ->with('error', $error);
        }

        $message = app()->getLocale() === 'id'
            ? 'Booking berhasil! Nomor booking: '.$booking->booking_number
            : 'Booking successful! Booking number: '.$booking->booking_number;

        return redirect()->route('events.show', $event->slug)
            ->with('message', $message);
    }
}
