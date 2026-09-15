<?php

namespace App\Services;

use App\Mail\BookingPaidNotification;
use App\Mail\BookingReceivedNotification;
use App\Mail\MeetingLinkNotification;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private readonly WhatsAppQuotaService $whatsAppQuotaService,
    ) {}

    /**
     * Deliver an online/hybrid event meeting link to one attendee
     * via every channel they have on file (email + WhatsApp).
     *
     * @return array{emailed: bool, whatsapped: bool} channels actually used
     */
    public function sendMeetingLink(User $user, Event $event): array
    {
        $channels = ['emailed' => false, 'whatsapped' => false];

        if ($user->email) {
            Mail::to($user->email)->queue(
                new MeetingLinkNotification($user, $event, app()->getLocale())
            );

            $channels['emailed'] = true;
        }

        if ($user->phone) {
            // Local driver: WhatsApp provider not integrated yet (same as OTP flow);
            // the payload is logged so the message is auditable and a provider
            // (Fonnte/Twilio) can be dropped in later without touching callers.
            Log::info("WA_MEETING_LINK_SENDING: Event '{$event->title}' ({$event->id}) link {$event->meeting_link} to {$user->phone}");

            $channels['whatsapped'] = true;
        }

        return $channels;
    }

    /**
     * "Booking received / awaiting payment" email is queued after checkout.
     *
     * @return array{emailed: bool, whatsapped: bool}
     */
    public function sendBookingReceived(Booking $booking): array
    {
        $channels = ['emailed' => false, 'whatsapped' => false];

        if ($booking->user?->email) {
            Mail::to($booking->user->email)->queue(
                new BookingReceivedNotification($booking, $this->ticketPayload($booking), app()->getLocale())
            );

            $channels['emailed'] = true;
        }

        return $channels;
    }

    /**
     * Payment success: deliver every ticket by the channels each attendee
     * supplied — email (per attendee) and/or WhatsApp (when the organizer
     * enabled it for the event and has quota).
     *
     * @return array{emailed: bool, whatsapped: bool}
     */
    public function sendPaymentSuccess(Booking $booking): array
    {
        $booking->loadMissing(['event.user', 'user', 'bookingTickets.ticket']);

        $channels = ['emailed' => false, 'whatsapped' => false];
        $buyerEmail = $booking->user?->email;

        // Buyer receipt with the full QR set (fallback / record)
        if ($buyerEmail) {
            Mail::to($buyerEmail)->queue(
                new BookingPaidNotification($booking, $this->ticketPayload($booking), app()->getLocale())
            );

            $channels['emailed'] = true;
        }

        // Per-attendee emails — one message per distinct attendee address
        foreach ($this->ticketsGroupedByEmail($booking, $buyerEmail) as $email => $tickets) {
            Mail::to($email)->queue(
                new BookingPaidNotification($booking, $tickets, app()->getLocale())
            );

            $channels['emailed'] = true;
        }

        $this->sendTicketWhatsApp($booking, $channels);

        return $channels;
    }

    /**
     * WhatsApp delivery, gated by the event toggle and the organizer's quota.
     * Each ticket consumes one unit; out-of-quota stops further sends.
     *
     * @param  array{emailed: bool, whatsapped: bool}  $channels
     */
    private function sendTicketWhatsApp(Booking $booking, array &$channels): void
    {
        if (! $booking->event?->whatsapp_enabled) {
            return;
        }

        $organizer = $booking->event->user;

        if (! $organizer) {
            return;
        }

        $sent = false;

        foreach ($booking->bookingTickets as $row) {
            if (! $row->attendee_phone) {
                continue;
            }

            if (! $this->whatsAppQuotaService->consume($organizer, 1)) {
                Log::warning("WA_TICKETS_SKIPPED_NO_QUOTA: organizer {$organizer->id} booking {$booking->booking_number} quota {$organizer->whatsapp_quota}");

                break;
            }

            Log::info(
                "WA_TICKETS_SENDING: Booking {$booking->booking_number} QR {$row->ticket_code} ".
                "({$row->attendee_name}) to {$row->attendee_phone}"
            );

            $sent = true;
        }

        // Fallback: no attendee numbers supplied → send the codes to the buyer.
        if (! $sent && ($buyerPhone = $booking->user?->phone)) {
            if ($this->whatsAppQuotaService->consume($organizer, 1)) {
                $codes = implode(', ', array_column($this->ticketPayload($booking), 'code'));

                Log::info("WA_TICKETS_SENDING: Booking {$booking->booking_number} QR codes [{$codes}] to {$buyerPhone}");

                $sent = true;
            }
        }

        $channels['whatsapped'] = $sent;
    }

    /**
     * Attendee rows grouped by email, excluding the buyer (who already got all).
     *
     * @return array<string, array<int, array{ticket_name: string, attendee_name: string|null, code: string}>>
     */
    private function ticketsGroupedByEmail(Booking $booking, ?string $buyerEmail): array
    {
        $grouped = [];

        foreach ($booking->bookingTickets as $row) {
            if (! $row->attendee_email || $row->attendee_email === $buyerEmail) {
                continue;
            }

            $grouped[$row->attendee_email][] = $this->rowPayload($row);
        }

        return $grouped;
    }

    /**
     * One entry per issued ticket (the QR payload is the ticket_code).
     *
     * @return array<int, array{ticket_name: string, attendee_name: string|null, code: string}>
     */
    private function ticketPayload(Booking $booking): array
    {
        $booking->loadMissing('bookingTickets.ticket');

        return $booking->bookingTickets
            ->map(fn ($row) => $this->rowPayload($row))
            ->all();
    }

    /**
     * @return array{ticket_name: string, attendee_name: string|null, code: string}
     */
    private function rowPayload($row): array
    {
        return [
            'ticket_name' => $row->ticket->name ?? 'Ticket',
            'attendee_name' => $row->attendee_name,
            'code' => $row->ticket_code,
        ];
    }
}
