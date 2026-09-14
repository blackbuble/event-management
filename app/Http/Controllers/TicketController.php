<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\StoreTicketRequest;
use App\Http\Requests\Web\UpdateTicketRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;

class TicketController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    /**
     * Add a ticket type to an event.
     */
    public function store(StoreTicketRequest $request, Event $event): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['is_active'] ??= true;

            $this->eventService->createTicket($event, $data);
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('events.edit', $event)->with('message', $this->text('created'));
    }

    /**
     * Update an existing ticket type.
     */
    public function update(UpdateTicketRequest $request, Event $event, Ticket $ticket): RedirectResponse
    {
        $this->ensureTicketBelongsToEvent($ticket, $event);

        try {
            $this->eventService->updateTicket($ticket, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('events.edit', $event)->with('message', $this->text('updated'));
    }

    /**
     * Remove a ticket type (blocked while active bookings exist).
     */
    public function destroy(Event $event, Ticket $ticket): RedirectResponse
    {
        $this->ensureTicketBelongsToEvent($ticket, $event);

        try {
            $this->eventService->deleteTicket($ticket);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->route('events.edit', $event)->with('error', $exception->getMessage());
        }

        return redirect()->route('events.edit', $event)->with('message', $this->text('deleted'));
    }

    private function ensureTicketBelongsToEvent(Ticket $ticket, Event $event): void
    {
        abort_unless($ticket->event_id === $event->id, 404);
    }

    private function text(string $action): string
    {
        $isId = app()->getLocale() === 'id';

        return match ($action) {
            'created' => $isId ? 'Tiket berhasil ditambahkan.' : 'Ticket created successfully.',
            'updated' => $isId ? 'Tiket berhasil diperbarui.' : 'Ticket updated successfully.',
            default => $isId ? 'Tiket berhasil dihapus.' : 'Ticket deleted successfully.',
        };
    }
}
