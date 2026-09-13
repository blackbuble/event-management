<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const BOOK_LOCK_TTL = 15;

    private const BOOK_LOCK_WAIT = 5;

    public function createBooking(Event $event, int $userId, array $ticketSelections): Booking
    {
        $lock = Cache::lock('booking-create:'.$event->id.':'.$userId, self::BOOK_LOCK_TTL);

        return $lock->block(self::BOOK_LOCK_WAIT, function () use ($event, $userId, $ticketSelections) {
            return DB::transaction(function () use ($event, $userId, $ticketSelections) {
                $totalAmount = 0;
                $ticketRows = [];

                foreach ($ticketSelections as $selection) {
                    $ticket = Ticket::query()
                        ->where('id', $selection['ticket_id'])
                        ->where('event_id', $event->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $quantity = (int) $selection['quantity'];

                    if (! $ticket->isAvailable()) {
                        throw new \InvalidArgumentException("Ticket \"{$ticket->name}\" is no longer available.");
                    }

                    $remaining = $ticket->remainingQuantity();
                    if ($quantity > $remaining) {
                        throw new \InvalidArgumentException("Only {$remaining} tickets remaining for \"{$ticket->name}\".");
                    }

                    if ($ticket->min_per_order && $quantity < $ticket->min_per_order) {
                        throw new \InvalidArgumentException("Minimum order for \"{$ticket->name}\" is {$ticket->min_per_order}.");
                    }

                    if ($ticket->max_per_order && $quantity > $ticket->max_per_order) {
                        throw new \InvalidArgumentException("Maximum order for \"{$ticket->name}\" is {$ticket->max_per_order}.");
                    }

                    $lineTotal = $ticket->price * $quantity;
                    $totalAmount += $lineTotal;

                    $ticket->increment('quantity_sold', $quantity);

                    $ticketRows[] = [
                        'ticket_id' => $ticket->id,
                        'quantity' => $quantity,
                        'price' => $ticket->price,
                    ];
                }

                $isFree = $totalAmount == 0;

                $booking = Booking::create([
                    'user_id' => $userId,
                    'event_id' => $event->id,
                    'status' => $isFree ? 'confirmed' : 'pending',
                    'total_amount' => $totalAmount,
                    'payment_status' => $isFree ? 'paid' : 'unpaid',
                ]);

                foreach ($ticketRows as $row) {
                    $booking->bookingTickets()->create($row);
                }

                return $booking;
            });
        });
    }
}
