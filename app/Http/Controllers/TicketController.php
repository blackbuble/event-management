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
    {    $this->authorize('update', $event);

    return view('tickets.create', compact('event'));}

    /**
     * Store new ticket
     */
    public function store(Request $request, Event $event)
    {<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['event_id', 'name'], 'tickets_event_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_event_id_name_unique');
        });
    }
};}

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