<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\TicketQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Payment success — carries one QR attachment per ticket. QR payload is the
 * ticket_code so the same codes can be re-sent over WhatsApp if needed.
 */
class BookingPaidNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{ticket_name: string, attendee_name: string|null, code: string}>  $tickets
     */
    public function __construct(
        public readonly Booking $booking,
        private readonly array $tickets = [],
        private readonly string $mailLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailLocale === 'id'
                ? 'Pembayaran Berhasil — Tiket '.$this->booking->booking_number
                : 'Payment Successful — Tickets '.$this->booking->booking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.paid',
            with: [
                'locale' => $this->mailLocale,
                'recipientName' => $this->booking->user?->name ?? 'Attendee',
                'eventName' => $this->booking->event?->title ?? 'Event',
                'bookingNumber' => $this->booking->booking_number,
                'totalAmount' => number_format((float) $this->booking->total_amount, 0, ',', '.'),
                'paymentMethod' => $this->booking->payment_method,
                'tickets' => $this->tickets,
                'showUrl' => URL::signedRoute('bookings.show', $this->booking->id),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $qr = app(TicketQrService::class);

        return array_map(
            fn (array $ticket) => Attachment::fromData(
                fn () => $qr->png($ticket['code']),
                "ticket-{$ticket['code']}.png",
            )->withMime('image/png'),
            $this->tickets,
        );
    }
}
