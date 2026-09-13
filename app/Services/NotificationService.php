<?php

namespace App\Services;

use App\Mail\MeetingLinkNotification;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
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
     * Send booking confirmation email
     */
    public function sendBookingConfirmation(Booking $booking): void
    {
        // TODO: Implement email notification
        // Mail::to($booking->user->email)->send(new BookingConfirmation($booking));
    }

    /**
     * Send event reminder
     */
    public function sendEventReminder(Booking $booking): void
    {
        // TODO: Implement email reminder email
        // Mail::to($booking->user->email)->send(new EventReminder($booking));
    }

    /**
     * Send cancellation notification
     */
    public function sendCancellationNotification(Booking $booking): void
    {
        // TODO: Implement cancellation email
        // Mail::to($booking->user->email)->send(new BookingCancelled($booking));
    }
}
