<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreBookingRequest;
use App\Http\Requests\Web\StorePaymentRequest;
use App\Models\Booking;
use App\Models\Event;
use App\Services\BookingService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    /**
     * Transaction page — the attendee enters participant names (one by one, or
     * via a single representative) before continuing to checkout.
     *
     * Public: seamless guest checkout, no account required.
     */
    public function create(Request $request, Event $event): Response|RedirectResponse
    {
        if ($event->status !== 'published') {
            abort(403);
        }

        if ($request->user() && (int) $request->user()->id === (int) $event->user_id) {
            return redirect()->route('events.show', $event->slug)->with('error', $this->text('own_event'));
        }

        $selections = $this->normalizeSelections($request->query('tickets', []));

        try {
            $preview = $this->bookingService->previewSelection($event, $selections);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('events.show', $event->slug)->with('error', $e->getMessage());
        }

        if (empty($preview['tickets'])) {
            return redirect()->route('events.show', $event->slug)->with('error', $this->text('no_selection'));
        }

        return Inertia::render('Bookings/Transaction', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'venue_name' => $event->venue_name,
                'start_date' => $event->start_date?->toIso8601String(),
            ],
            'selection' => $preview['tickets'],
            'total_amount' => $preview['total_amount'],
        ]);
    }

    public function store(StoreBookingRequest $request, Event $event): RedirectResponse
    {
        if ($event->status !== 'published') {
            abort(403);
        }

        $validated = $request->validated();

        try {
            $booking = $this->bookingService->createBooking(
                $event,
                $request->user()?->id,
                $validated['tickets'],
                $validated['attendee_mode'],
                $validated['representative'] ?? [],
                $validated['contact'],
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('events.show', $event->slug)->with('error', $e->getMessage());
        } catch (LockTimeoutException) {
            return redirect()->route('events.show', $event->slug)->with('error', $this->text('busy'));
        }

        // Free bookings are already settled; paid bookings must pick a payment method.
        if ($booking->payment_status === 'paid') {
            return redirect()->to(URL::signedRoute('bookings.show', $booking))
                ->with('message', $this->text('free_confirmed'));
        }

        return redirect()->to(URL::signedRoute('bookings.pay', $booking))
            ->with('message', $this->text('created'));
    }

    /**
     * Booking confirmation / receipt (owner policy or signed URL).
     */
    public function show(Booking $booking): Response
    {
        $summary = $this->bookingService->summary($booking);

        return Inertia::render('Bookings/Show', [
            'booking' => $summary,
            'pay_url' => $summary['payment_status'] === 'paid'
                ? null
                : URL::signedRoute('bookings.pay', $booking),
        ]);
    }

    /**
     * Checkout — payment method selection for a pending booking.
     */
    public function pay(Booking $booking): Response|RedirectResponse
    {
        if ($booking->payment_status === 'paid') {
            return redirect()->to(URL::signedRoute('bookings.show', $booking));
        }

        return Inertia::render('Bookings/Pay', [
            'booking' => $this->bookingService->summary($booking),
            'payment_methods' => PaymentMethod::options(app()->getLocale()),
            'submit_url' => URL::signedRoute('bookings.pay.store', $booking),
        ]);
    }

    /**
     * Settle the booking with the chosen payment method.
     */
    public function processPayment(StorePaymentRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->bookingService->pay(
                $booking,
                PaymentMethod::from($request->validated('payment_method')),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(URL::signedRoute('bookings.pay', $booking))->with('error', $e->getMessage());
        }

        return redirect()->to(URL::signedRoute('bookings.show', $booking))->with('message', $this->text('paid'));
    }

    /**
     * Normalise the querystring ticket selection into a strict int/positive shape.
     *
     * @return array<int, array{ticket_id: int, quantity: int, attendees: array<int, never>}>
     */
    private function normalizeSelections(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $rows = array_values(array_filter($raw, 'is_array'));

        // `brackets`-style query strings (tickets[][ticket_id]=..&tickets[][quantity]=..)
        // are parsed by PHP into consecutive single-key rows. Merge complementary
        // neighbours so both indexed and bracket payloads resolve identically.
        $selections = [];

        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];

            if (! isset($row['ticket_id']) && isset($rows[$i + 1]['ticket_id'])) {
                $row = array_merge($row, $rows[$i + 1]);
                $i++;
            } elseif (! isset($row['quantity']) && isset($rows[$i + 1]['quantity'])) {
                $row = array_merge($row, $rows[$i + 1]);
                $i++;
            }

            $ticketId = (int) ($row['ticket_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($ticketId > 0 && $quantity > 0) {
                $selections[] = [
                    'ticket_id' => $ticketId,
                    'quantity' => $quantity,
                    'attendees' => [],
                ];
            }
        }

        return $selections;
    }

    private function text(string $key): string
    {
        $isId = app()->getLocale() === 'id';

        return match ($key) {
            'created' => $isId
                ? 'Booking dibuat. Silakan pilih metode pembayaran.'
                : 'Booking created. Please choose a payment method.',
            'free_confirmed' => $isId
                ? 'Booking gratis Anda terkonfirmasi!'
                : 'Your free booking is confirmed!',
            'paid' => $isId
                ? 'Pembayaran berhasil. Tiket Anda terkonfirmasi.'
                : 'Payment successful. Your ticket is confirmed.',
            'own_event' => $isId
                ? 'Penyelenggara tidak dapat membeli tiket untuk event sendiri.'
                : 'Organizers cannot buy tickets for their own event.',
            'no_selection' => $isId
                ? 'Pilih tiket terlebih dahulu sebelum melanjutkan.'
                : 'Please select tickets before continuing.',
            default => $isId
                ? 'Permintaan sebelumnya masih diproses. Mohon tunggu sebentar dan coba lagi.'
                : 'A previous request is still being processed. Please wait a moment and try again.',
        };
    }
}
