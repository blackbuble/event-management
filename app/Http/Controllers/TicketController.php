<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use App\Services\EventService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
        $this->middleware('auth');
    }

    /**
     * Show create ticket form
     */
    public function create(Event $event)
    {
        $this->authorize('update', $event);
        
        return view('tickets.create', compact('event'));
    }

    /**
     * Store new ticket
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'sale_starts' => 'nullable|date',
            'sale_ends' => 'nullable|date|after:sale_starts',
            'min_per_order' => 'required|integer|min:1',
            'max_per_order' => 'required|integer|min:1|gte:min_per_order',
            'is_active' => 'boolean',
        ]);

        try {
            $this->eventService->createTicket($event, $validated);

            return redirect()->route('events.edit', $event)
                ->with('success', 'Ticket created successfully!');
        } catch (\Exception $e) {
            return back()->withInput()
                ->withErrors(['error' => 'Failed to create ticket: ' . $e->getMessage()]);
        }
    }

    /**
     * Show edit ticket form
     */
    public function edit(Event $event, Ticket $ticket)
    {
        $this->authorize('update', $event);

        if ($ticket->event_id !== $event->id) {
            abort(404);
        }

        return view('tickets.edit', compact('event', 'ticket'));
    }

    /**
     * Update ticket
     */
    public function update(Request $request, Event $event, Ticket $ticket)
    {
        $this->authorize('update', $event);

        if ($ticket->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'sale_starts' => 'nullable|date',
            'sale_ends' => 'nullable|date|after:sale_starts',
            'min_per_order' => 'required|integer|min:1',
            'max_per_order' => 'required|integer|min:1|gte:min_per_order',
            'is_active' => 'boolean',
        ]);

        try {
            $this->eventService->updateTicket($ticket, $validated);

            return redirect()->route('events.edit', $event)
                ->with('success', 'Ticket updated successfully!');
        } catch (\Exception $e) {
            return back()->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete ticket
     */
    public function destroy(Event $event, Ticket $ticket)
    {
        $this->authorize('update', $event);

        if ($ticket->event_id !== $event->id) {
            abort(404);
        }

        try {
            $this->eventService->deleteTicket($ticket);

            return redirect()->route('events.edit', $event)
                ->with('success', 'Ticket deleted successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}