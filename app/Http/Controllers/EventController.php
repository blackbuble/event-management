<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * Display list of events
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'type', 'date_from', 'date_to', 'is_free']);
        $events = $this->eventService->getAvailableEvents($filters);

        return view('events.index', compact('events'));
    }

    /**
     * Show single event
     */
    public function show($slug)
    {
        $event = Event::where('slug', $slug)
            ->with(['user', 'tickets' => function($query) {
                $query->where('is_active', true);
            }])
            ->firstOrFail();

        return view('events.show', compact('event'));
    }

    /**
     * Show create event form
     */
    public function create()
    {
        return view('events.create');
    }

    /**
     * Store new event
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'description' => 'required',
            'image' => 'nullable|image|max:2048',
            'venue_name' => 'required|max:255',
            'venue_address' => 'required',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'type' => 'required|in:online,offline,hybrid',
            'meeting_link' => 'nullable|url|required_if:type,online,hybrid',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published',
        ]);

        try {
            $event = $this->eventService->createEvent($validated, auth()->id());

            return redirect()->route('events.show', $event->slug)
                ->with('success', 'Event created successfully!');
        } catch (\Exception $e) {
            return back()->withInput()
                ->withErrors(['error' => 'Failed to create event: ' . $e->getMessage()]);
        }
    }

    /**
     * Show edit event form
     */
    public function edit(Event $event)
    {
        $this->authorize('update', $event);
        
        return view('events.edit', compact('event'));
    }

    /**
     * Update event
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'required|max:255',
            'description' => 'required',
            'image' => 'nullable|image|max:2048',
            'venue_name' => 'required|max:255',
            'venue_address' => 'required',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'type' => 'required|in:online,offline,hybrid',
            'meeting_link' => 'nullable|url|required_if:type,online,hybrid',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,cancelled',
        ]);

        try {
            $event = $this->eventService->updateEvent($event, $validated);

            return redirect()->route('events.show', $event->slug)
                ->with('success', 'Event updated successfully!');
        } catch (\Exception $e) {
            return back()->withInput()
                ->withErrors(['error' => 'Failed to update event: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete event
     */
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        try {
            $this->eventService->deleteEvent($event);

            return redirect()->route('dashboard.events')
                ->with('success', 'Event deleted successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}