<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const BOOK_LOCK_TTL = 15;

    private const BOOK_LOCK_WAIT = 5;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Validate the selected quantities without persisting anything — used to
     * render the transaction page (attendee entry) before checkout.
     *
     * @param  array<int, array{ticket_id: int, quantity: int}>  $selections
     * @return array{tickets: array<int, array<string, mixed>>, total_amount: float}
     */
    public function previewSelection(Event $event, array $selections): array
    {
        $tickets = [];
        $total = 0.0;

        foreach ($selections as $selection) {
            $ticket = $event->tickets()
                ->whereKey($selection['ticket_id'])
                ->where('is_active', true)
                ->first();

            if (! $ticket) {
                throw new \InvalidArgumentException($this->text('ticket_unavailable'));
            }

            $quantity = (int) $selection['quantity'];
            $this->assertTicketSelectable($ticket, $quantity);

            $subtotal = (float) $ticket->price * $quantity;
            $total += $subtotal;

            $tickets[] = [
                'ticket_id' => $ticket->id,
                'name' => $ticket->name,
                'price' => (float) $ticket->price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        return ['tickets' => $tickets, 'total_amount' => $total];
    }

    /**
     * Create a booking for an attendee.
     *
     * Organizers cannot buy tickets for their own event (self-dealing guard);
     * enforced here — not only in the UI — so the route cannot be gamed.
     *
     * Each seat becomes its own row (quantity 1, unique ticket_code) so every
     * attendee gets their own scannable QR. `attendee_mode` decides whether the
     * names come in one-by-one or from a single representative.
     *
     * @param  array<int, array{ticket_id: int, quantity: int, attendees?: array<int, array{name: string, email?: string|null}>}>  $selections
     * @param  array{name?: string, email?: string|null}  $representative
     * @param  array{name?: string, email?: string|null}  $contact
     */
    public function createBooking(
        Event $event,
        ?int $userId,
        array $selections,
        string $attendeeMode = 'representative',
        array $representative = [],
        array $contact = [],
    ): Booking {
        $buyer = $this->resolveBuyer($userId, $contact);

        if ((int) $event->user_id === (int) $buyer->id) {
            throw new \InvalidArgumentException($this->text('own_event'));
        }

        $lock = Cache::lock('booking-create:'.$event->id.':'.$buyer->id, self::BOOK_LOCK_TTL);

        $booking = $lock->block(self::BOOK_LOCK_WAIT, function () use ($event, $buyer, $selections, $attendeeMode, $representative) {
            return DB::transaction(function () use ($event, $buyer, $selections, $attendeeMode, $representative) {
                $totalAmount = 0;
                $ticketRows = [];

                foreach ($selections as $selection) {
                    $ticket = Ticket::query()
                        ->where('id', $selection['ticket_id'])
                        ->where('event_id', $event->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $quantity = (int) $selection['quantity'];
                    $this->assertTicketSelectable($ticket, $quantity);

                    $totalAmount += $ticket->price * $quantity;
                    $ticket->increment('quantity_sold', $quantity);

                    foreach ($this->attendeesFor($selection, $attendeeMode, $representative) as $attendee) {
                        $ticketRows[] = [
                            'ticket_id' => $ticket->id,
                            'quantity' => 1,
                            'price' => $ticket->price,
                            'attendee_name' => $attendee['name'],
                            'attendee_email' => $attendee['email'] ?? null,
                            'attendee_phone' => $attendee['phone'] ?? null,
                        ];
                    }
                }

                $isFree = $totalAmount == 0;

                $booking = Booking::create([
                    'user_id' => $buyer->id,
                    'event_id' => $event->id,
                    'status' => $isFree ? 'confirmed' : 'pending',
                    'total_amount' => $totalAmount,
                    'payment_status' => $isFree ? 'paid' : 'unpaid',
                ]);

                $booking->bookingTickets()->createMany($ticketRows);

                return $booking;
            });
        });

        $this->notifyBookingCreated($booking);

        return $booking;
    }

    /**
     * Settle a booking with the attendee's chosen payment method and deliver
     * the ticket QRs (email + WhatsApp hook).
     *
     * Idempotent: an already-paid booking is returned untouched; a cancelled
     * booking cannot be revived via payment.
     */
    public function pay(Booking $booking, PaymentMethod $method): Booking
    {
        if ($booking->status === 'cancelled') {
            throw new \InvalidArgumentException($this->text('cancelled'));
        }

        if ($booking->payment_status === 'paid') {
            return $booking;
        }

        $settled = $this->bookingRepository->markPaid($booking, $method);

        // Lost the race: another request settled this booking. Return without
        // re-sending the confirmation / re-consuming delivery quota.
        if ($settled === null) {
            return $booking->fresh();
        }

        $settled->loadMissing(['event', 'user', 'bookingTickets.ticket']);
        $this->notificationService->sendPaymentSuccess($settled);

        return $settled;
    }

    /**
     * Lean booking payload for the payment / confirmation pages.
     *
     * @return array<string, mixed>
     */
    public function summary(Booking $booking): array
    {
        $booking->loadMissing(['event:id,title,slug,start_date,end_date', 'bookingTickets.ticket:id,name']);

        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'payment_method' => $booking->payment_method,
            'total_amount' => (float) $booking->total_amount,
            'event' => $booking->event ? [
                'id' => $booking->event->id,
                'title' => $booking->event->title,
                'slug' => $booking->event->slug,
                'start_date' => $booking->event->start_date?->toIso8601String(),
                'end_date' => $booking->event->end_date?->toIso8601String(),
            ] : null,
            'tickets' => $booking->bookingTickets
                ->map(fn ($row) => [
                    'name' => $row->ticket?->name,
                    'attendee_name' => $row->attendee_name,
                    'ticket_code' => $row->ticket_code,
                    'quantity' => (int) $row->quantity,
                    'price' => (float) $row->price,
                    'subtotal' => (float) $row->price * (int) $row->quantity,
                ])
                ->all(),
        ];
    }

    /**
     * Resolve the booking owner: the authenticated buyer, or (seamless guest
     * checkout) a passwordless attendee account keyed by the contact email.
     *
     * @param  array{name?: string, email?: string|null}  $contact
     */
    private function resolveBuyer(?int $userId, array $contact): User
    {
        if ($userId) {
            $buyer = $this->userRepository->findById($userId);

            if ($buyer) {
                return $buyer;
            }
        }

        $email = (string) ($contact['email'] ?? '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException($this->text('contact_required'));
        }

        return $this->userRepository->findOrCreateAttendeeByEmail($email, $contact['name'] ?? null);
    }

    /**
     * Resolve one attendee entry per seat for a ticket selection.
     *
     * @param  array{ticket_id: int, quantity: int, attendees?: array<int, array{name: string, email?: string|null}>}  $selection
     * @param  array{name?: string, email?: string|null}  $representative
     * @return array<int, array{name: string, email: string|null}>
     */
    private function attendeesFor(array $selection, string $attendeeMode, array $representative): array
    {
        if ($attendeeMode === 'representative') {
            $attendee = [
                'name' => (string) ($representative['name'] ?? ''),
                'email' => $representative['email'] ?? null,
                'phone' => $representative['phone'] ?? null,
            ];

            return array_fill(0, (int) $selection['quantity'], $attendee);
        }

        return array_map(
            fn (array $attendee) => [
                'name' => (string) ($attendee['name'] ?? ''),
                'email' => $attendee['email'] ?? null,
                'phone' => $attendee['phone'] ?? null,
            ],
            $selection['attendees'] ?? [],
        );
    }

    private function assertTicketSelectable(Ticket $ticket, int $quantity): void
    {
        if (! $ticket->isAvailable()) {
            throw new \InvalidArgumentException(
                app()->getLocale() === 'id'
                    ? "Tiket \"{$ticket->name}\" sudah tidak tersedia."
                    : "Ticket \"{$ticket->name}\" is no longer available."
            );
        }

        $remaining = $ticket->remainingQuantity();
        if ($quantity > $remaining) {
            throw new \InvalidArgumentException(
                app()->getLocale() === 'id'
                    ? "Hanya {$remaining} tiket tersisa untuk \"{$ticket->name}\"."
                    : "Only {$remaining} tickets remaining for \"{$ticket->name}\"."
            );
        }

        if ($ticket->min_per_order && $quantity < $ticket->min_per_order) {
            throw new \InvalidArgumentException(
                app()->getLocale() === 'id'
                    ? "Minimum order untuk \"{$ticket->name}\" adalah {$ticket->min_per_order}."
                    : "Minimum order for \"{$ticket->name}\" is {$ticket->min_per_order}."
            );
        }

        if ($ticket->max_per_order && $quantity > $ticket->max_per_order) {
            throw new \InvalidArgumentException(
                app()->getLocale() === 'id'
                    ? "Maksimum order untuk \"{$ticket->name}\" adalah {$ticket->max_per_order}."
                    : "Maximum order for \"{$ticket->name}\" is {$ticket->max_per_order}."
            );
        }
    }

    private function notifyBookingCreated(Booking $booking): void
    {
        $booking->loadMissing(['event', 'user', 'bookingTickets.ticket']);

        if ($booking->payment_status === 'paid') {
            $this->notificationService->sendPaymentSuccess($booking);

            return;
        }

        $this->notificationService->sendBookingReceived($booking);
    }

    private function text(string $key): string
    {
        $isId = app()->getLocale() === 'id';

        return match ($key) {
            'own_event' => $isId
                ? 'Penyelenggara tidak dapat membeli tiket untuk event sendiri.'
                : 'Organizers cannot buy tickets for their own event.',
            'contact_required' => $isId
                ? 'Email kontak wajib diisi untuk pengiriman tiket.'
                : 'A contact email is required to deliver your tickets.',
            'cancelled' => $isId
                ? 'Booking yang dibatalkan tidak dapat dibayar.'
                : 'A cancelled booking cannot be paid.',
            default => $isId
                ? 'Tiket yang dipilih sudah tidak tersedia.'
                : 'The selected ticket is no longer available.',
        };
    }
}
