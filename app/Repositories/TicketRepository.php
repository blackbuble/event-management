<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketRepository
{
    /**
     * Create a ticket for an event.
     */
    public function createForEvent(Event $event, array $attributes): Ticket
    {
        $attributes['event_id'] = $event->id;

        return DB::transaction(function () use ($event, $attributes) {
            unset($attributes['id']);

            return $event->tickets()->create($attributes);
        });
    }

    /**
     * Update a ticket, refusing to reduce quantity below already sold/reserved.
     */
    public function update(Ticket $ticket, array $attributes): Ticket
    {
        return DB::transaction(function () use ($ticket, $attributes) {
            unset($attributes['id']);

            // Re-read under a row lock so the guard sees the latest sold/reserved
            // counts even if a concurrent booking incremented them since load.
            $locked = $this->lockRow($ticket);
            $this->guardQuantityReduction($locked, $attributes);

            $locked->update($attributes);

            return $locked->fresh();
        });
    }

    /**
     * Delete a ticket, refusing when it still has active bookings.
     */
    public function delete(Ticket $ticket): bool
    {
        return DB::transaction(function () use ($ticket) {
            $locked = $this->lockRow($ticket);
            $this->guardDeletion($locked);

            return (bool) $locked->delete();
        });
    }

    /**
     * Reconcile the full ticket set of an event with the submitted payload:
     * rows carrying an `id` are updated, the rest created, and removed rows
     * deleted (guarded against sales). Runs in a single transaction.
     *
     * @param  array<int, array<string, mixed>>  $tickets
     */
    public function syncForEvent(Event $event, array $tickets): void
    {
        DB::transaction(function () use ($event, $tickets) {
            $keptIds = [];

            foreach ($tickets as $attributes) {
                $id = $attributes['id'] ?? null;
                unset($attributes['id']);
                $attributes['event_id'] = $event->id;

                if ($id) {
                    $existing = $event->tickets()->lockForUpdate()->find($id);

                    if ($existing) {
                        $this->guardQuantityReduction($existing, $attributes);
                        $existing->update($attributes);
                        $keptIds[] = $existing->id;

                        continue;
                    }
                }

                $keptIds[] = $event->tickets()->create($attributes)->id;
            }

            $event->tickets()
                ->whereNotIn('id', $keptIds)
                ->lockForUpdate()
                ->get()
                ->each(function (Ticket $ticket) {
                    $this->guardDeletion($ticket);
                    $ticket->delete();
                });
        });
    }

    /**
     * Fetch the ticket fresh under a pessimistic row lock.
     */
    private function lockRow(Ticket $ticket): Ticket
    {
        return Ticket::query()
            ->whereKey($ticket->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Guard: a ticket's quantity can never drop below the amount already
     * allocated (sold + reserved), otherwise inventory integrity breaks.
     */
    private function guardQuantityReduction(Ticket $ticket, array $attributes): void
    {
        if (! isset($attributes['quantity'])) {
            return;
        }

        $used = $ticket->quantity_sold + $ticket->quantity_reserved;

        if ((int) $attributes['quantity'] < $used) {
            throw new \InvalidArgumentException(
                "Cannot reduce quantity below {$used} (sold: {$ticket->quantity_sold}, reserved: {$ticket->quantity_reserved})"
            );
        }
    }

    /**
     * Guard: tickets referenced by non-cancelled bookings cannot be deleted
     * (would orphan attendee entitlements via FK cascade).
     */
    private function guardDeletion(Ticket $ticket): void
    {
        $hasActiveBookings = $ticket->bookingTickets()
            ->whereHas('booking', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->exists();

        if ($hasActiveBookings) {
            throw new \InvalidArgumentException('Cannot delete ticket with active bookings.');
        }
    }
}
