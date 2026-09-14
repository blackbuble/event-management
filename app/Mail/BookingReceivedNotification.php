<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * "We received your booking" — sent right after checkout while payment is still
 * pending. The payment-success email carries the QR codes.
 */
class BookingReceivedNotification extends Mailable implements ShouldQueue
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
                ? 'Booking Diterima: '.$this->booking->booking_number
                : 'Booking Received: '.$this->booking->booking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.received',
            with: [
                'locale' => $this->mailLocale,
                'recipientName' => $this->booking->user?->name ?? 'Attendee',
                'eventName' => $this->booking->event?->title ?? 'Event',
                'bookingNumber' => $this->booking->booking_number,
                'totalAmount' => number_format((float) $this->booking->total_amount, 0, ',', '.'),
                'paymentStatus' => $this->booking->payment_status,
                'tickets' => $this->tickets,
                'payUrl' => URL::signedRoute('bookings.pay', $this->booking->id),
            ],
        );
    }
}
