<?php

namespace Tests\Unit;

use App\Mail\BookingPaidNotification;
use App\Models\Booking;
use App\Services\TicketQrService;
use Tests\TestCase;

class TicketQrServiceTest extends TestCase
{
    public function test_generates_a_png_binary(): void
    {
        $png = (new TicketQrService)->png('TK-ABC123');

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertGreaterThan(100, strlen($png));
    }

    public function test_generates_a_data_uri(): void
    {
        $uri = (new TicketQrService)->dataUri('TK-ABC123');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_paid_notification_attaches_one_qr_per_ticket(): void
    {
        $booking = new Booking(['booking_number' => 'BK-TEST', 'total_amount' => 100000]);

        $mailable = new BookingPaidNotification($booking, [
            ['ticket_name' => 'Regular', 'attendee_name' => 'Andi', 'code' => 'TK-AAA'],
            ['ticket_name' => 'Regular', 'attendee_name' => 'Bunga', 'code' => 'TK-BBB'],
        ]);

        $this->assertCount(2, $mailable->attachments());
    }
}
