<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use App\Services\BookingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $eventService;
    protected $bookingService;

    public function __construct(
        EventService $eventService,
        BookingService $bookingService
    ) {
        $this->eventService = $eventService;
        $this->bookingService = $bookingService;
        $this->middleware('auth');
    }

    /**
     * Dashboard home
     */
    public function index()
    {
        $user = auth()->user();
        
        // User's upcoming bookings
        $upcomingBookings = $user->bookings()
            ->with('event')
            ->whereHas('event', function($q) {
                $q->where('start_date', '>=', now());
            })
            ->where('status', 'confirmed')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // User's events (if organizer)
        $myEvents = $user->events()
            ->withCount('bookings')
            ->orderBy('start_date', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard.index', compact('upcomingBookings', 'myEvents'));
    }

    /**
     * List user's events
     */
    public function events(Request $request)
    {
        $query = auth()->user()->events()
            ->withCount('bookings')
            ->with('tickets');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('dashboard.events', compact('events'));
    }

    /**
     * Show event management page
     */
    public function manageEvent(Event $event)
    {
        $this->authorize('update', $event);

        $event->load(['tickets', 'bookings.user']);
        $statistics = $this->eventService->getEventStatistics($event);

        return view('dashboard.manage-event', compact('event', 'statistics'));
    }

    /**
     * List event bookings
     */
    public function eventBookings(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $filters = $request->only(['status', 'payment_status', 'search']);
        $bookings = $this->bookingService->getEventBookings($event, $filters);

        return view('dashboard.event-bookings', compact('event', 'bookings'));
    }

    /**
     * Check-in page
     */
    public function checkIn(Event $event)
    {
        $this->authorize('update', $event);

        return view('dashboard.check-in', compact('event'));
    }

    /**
     * Process check-in
     */
    public function processCheckIn(Request $request)
    {
        $validated = $request->validate([
            'ticket_code' => 'required|string',
        ]);

        try {
            $bookingTicket = $this->bookingService->checkIn($validated['ticket_code']);

            return response()->json([
                'success' => true,
                'message' => 'Check-in successful!',
                'data' => [
                    'attendee' => $bookingTicket->attendee_name ?? $bookingTicket->booking->user->name,
                    'ticket_type' => $bookingTicket->ticket->name,
                    'checked_in_at' => $bookingTicket->checked_in_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}