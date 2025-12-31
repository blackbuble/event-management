<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Booking;
use App\Models\BookingReservation;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
        $this->middleware('auth');
    }

    /**
     * Step 1: Create reservation
     */
    public function reserve(Request $request, Event $event)
    {
        $validated = $request->validate([
            'tickets' => 'required|array|min:1',
            'tickets.*.ticket_id' => 'required|exists:tickets,id',
            'tickets.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $reservation = $this->bookingService->createReservation(
                auth()->id(),
                $event,
                $validated['tickets']
            );

            return redirect()->route('bookings.checkout', $reservation->reservation_token)
                ->with('success', 'Tickets reserved! Please complete your booking within 10 minutes.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Step 2: Show checkout page
     */
    public function checkout($reservationToken)
    {
        $reservation = BookingReservation::where('reservation_token', $reservationToken)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (!$reservation->isActive()) {
            $this->bookingService->cancelReservation($reservation);
            return redirect()->route('events.show', $reservation->event->slug)
                ->withErrors(['error' => 'Reservation has expired. Please try again.']);
        }

        $reservation->load('event');
        $total = $reservation->calculateTotal();

        return view('bookings.checkout', compact('reservation', 'total'));
    }

    /**
     * Step 3: Confirm booking (after payment)
     */
    public function confirm(Request $request, $reservationToken)
    {
        $reservation = BookingReservation::where('reservation_token', $reservationToken)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'payment_intent_id' => 'nullable|string|max:255',
        ]);

        try {
            $booking = $this->bookingService->confirmBooking($reservation, $validated);

            // If free event, mark as confirmed and paid
            if ($booking->total_amount == 0) {
                $this->bookingService->updatePaymentStatus($booking, 'free');
            }

            return redirect()->route('bookings.show', $booking)
                ->with('success', 'Booking confirmed successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel reservation before checkout
     */
    public function cancelReservation($reservationToken)
    {
        $reservation = BookingReservation::where('reservation_token', $reservationToken)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $this->bookingService->cancelReservation($reservation);

            return redirect()->route('events.show', $reservation->event->slug)
                ->with('success', 'Reservation cancelled');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Show booking details
     */
    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);
        
        $booking->load(['event', 'bookingTickets.ticket']);
        
        return view('bookings.show', compact('booking'));
    }

    /**
     * Cancel booking
     */
    public function cancel(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->bookingService->cancelBooking($booking, $validated['reason'] ?? null);

            return redirect()->route('bookings.show', $booking)
                ->with('success', 'Booking cancelled successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * List user's bookings
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'upcoming']);
        $bookings = $this->bookingService->getUserBookings(auth()->id(), $filters);

        return view('bookings.index', compact('bookings'));
    }

    /**
     * Download ticket (PDF)
     */
    public function downloadTicket(Booking $booking)
    {
        $this->authorize('view', $booking);

        // TODO: Generate PDF ticket
        // return PDF::loadView('bookings.ticket-pdf', compact('booking'))->download();
        
        return back()->with('info', 'PDF download feature coming soon');
    }
}