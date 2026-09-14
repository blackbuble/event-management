<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Repositories\BookingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): Booking
    {
        $organizer = User::factory()->create();
        $buyer = User::factory()->create();

        $event = Event::create([
            'user_id' => $organizer->id,
            'title' => 'Race Event',
            'description' => 'x',
            'venue_name' => 'V',
            'venue_address' => 'A',
            'type' => 'offline',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(3),
            'status' => 'published',
        ]);

        return Booking::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'total_amount' => 100000,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_mark_paid_only_settles_once(): void
    {
        $booking = $this->booking();
        $repository = app(BookingRepository::class);

        $first = $repository->markPaid($booking, PaymentMethod::Qris);

        $this->assertNotNull($first);
        $this->assertSame('paid', $first->payment_status);
        $this->assertSame('confirmed', $first->status);

        // A concurrent double-submit that lost the lock gets null (no duplicate delivery).
        $second = $repository->markPaid($first, PaymentMethod::Qris);

        $this->assertNull($second);
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }
}
