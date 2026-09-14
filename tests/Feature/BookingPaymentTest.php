<?php

namespace Tests\Feature;

use App\Mail\BookingPaidNotification;
use App\Mail\BookingReceivedNotification;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function organizer(): User
    {
        $user = User::factory()->create(['phone' => fake()->unique()->numerify('+62812#########')]);
        $user->assignRole('organizer');

        return $user;
    }

    private function attendee(): User
    {
        $user = User::factory()->create(['phone' => fake()->unique()->numerify('+62898#########')]);
        $user->assignRole('attendee');

        return $user;
    }

    private function event(User $organizer, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'user_id' => $organizer->id,
            'title' => 'Community Meetup',
            'description' => 'Monthly gathering.',
            'venue_name' => 'Co-working Space',
            'venue_address' => 'Bandung',
            'type' => 'offline',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(3),
            'status' => 'published',
        ], $overrides));
    }

    private function ticket(Event $event, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 100000,
            'quantity' => 50,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Mirrors the real frontend payload: both branches are always posted
     * (empty strings in the inactive branch) so validation stays honest.
     */
    private function bookPayload(Ticket $ticket, int $quantity = 1, string $mode = 'representative', ?array $attendees = null): array
    {
        $blankAttendees = fn (int $count) => array_fill(0, $count, ['name' => '', 'email' => '']);

        if ($mode === 'individual') {
            $attendees ??= array_map(
                fn (int $index) => ['name' => 'Attendee '.($index + 1), 'email' => ''],
                range(0, $quantity - 1),
            );

            return [
                'attendee_mode' => 'individual',
                'contact' => ['name' => 'Budi Perwakilan', 'email' => 'budi@example.com'],
                'tickets' => [[
                    'ticket_id' => $ticket->id,
                    'quantity' => $quantity,
                    'attendees' => $attendees,
                ]],
                'representative' => ['name' => '', 'email' => ''],
            ];
        }

        return [
            'attendee_mode' => 'representative',
            'contact' => ['name' => 'Budi Perwakilan', 'email' => 'budi@example.com'],
            'tickets' => [[
                'ticket_id' => $ticket->id,
                'quantity' => $quantity,
                'attendees' => $blankAttendees($quantity),
            ]],
            'representative' => ['name' => 'Budi Perwakilan', 'email' => 'budi@example.com'],
        ];
    }

    public function test_organizer_cannot_book_their_own_event(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $ticket = $this->ticket($event);

        $this->actingAs($organizer)
            ->post(route('bookings.store', $event), $this->bookPayload($ticket))
            ->assertRedirect(route('events.show', $event->slug))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, $ticket->fresh()->quantity_sold);
    }

    public function test_paid_booking_redirects_to_payment_method(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $response = $this->actingAs($attendee)
            ->post(route('bookings.store', $event), $this->bookPayload($ticket));

        $booking = Booking::firstOrFail();

        $response->assertRedirect(URL::signedRoute('bookings.pay', $booking));

        $this->assertSame('pending', $booking->status);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame($attendee->id, $booking->user_id);
    }

    public function test_free_booking_is_confirmed_and_redirects_to_details(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event, ['price' => 0]);
        $attendee = $this->attendee();

        $response = $this->actingAs($attendee)
            ->post(route('bookings.store', $event), $this->bookPayload($ticket));

        $booking = Booking::firstOrFail();

        $response->assertRedirect(URL::signedRoute('bookings.show', $booking));
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
    }

    public function test_booking_owner_can_view_payment_page(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->get(route('bookings.pay', $booking))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Bookings/Pay')
                    ->where('booking.id', $booking->id)
                    ->has('payment_methods', 5)
            );
    }

    public function test_other_users_cannot_view_or_pay_someone_elses_booking(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $intruder = $this->attendee();

        $this->actingAs($intruder)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($intruder)->get(route('bookings.pay', $booking))->assertForbidden();
        $this->actingAs($intruder)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'bank_transfer'])
            ->assertForbidden();
    }

    public function test_owner_can_pay_and_booking_becomes_confirmed(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'bank_transfer'])
            ->assertRedirect(URL::signedRoute('bookings.show', $booking))
            ->assertSessionHas('message');

        $booking->refresh();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('bank_transfer', $booking->payment_method);
    }

    public function test_invalid_payment_method_is_rejected(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'crypto'])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    public function test_cancelled_booking_cannot_be_paid(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();
        $booking->update(['status' => 'cancelled']);

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'qris'])
            ->assertRedirect(URL::signedRoute('bookings.pay', $booking))
            ->assertSessionHas('error');

        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    public function test_already_paid_booking_redirects_to_details(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event, ['price' => 0]);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->get(route('bookings.pay', $booking))
            ->assertRedirect(URL::signedRoute('bookings.show', $booking));
    }

    public function test_seamless_guest_checkout_requires_no_login(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);

        // No actingAs — a plain guest buys with only a contact email.
        $response = $this->post(route('bookings.store', $event), $this->bookPayload($ticket));

        $booking = Booking::firstOrFail();

        $response->assertRedirect(URL::signedRoute('bookings.pay', $booking));
        $this->assertNotNull($booking->user_id);
        $this->assertSame('budi@example.com', $booking->user->email);

        // The emailed signed link lets the guest pay without logging in.
        $this->get(URL::signedRoute('bookings.pay', $booking))->assertOk();
        $this->post(URL::signedRoute('bookings.pay.store', $booking), ['payment_method' => 'qris'])
            ->assertRedirect(URL::signedRoute('bookings.show', $booking));

        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_unsigned_guest_cannot_access_booking_routes(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);

        $this->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $this->get(route('bookings.show', $booking))->assertForbidden();
        $this->get(route('bookings.pay', $booking))->assertForbidden();
        $this->post(route('bookings.pay.store', $booking), ['payment_method' => 'qris'])->assertForbidden();
    }

    public function test_transaction_page_lists_the_selection(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $url = route('bookings.create', [
            'event' => $event->id,
            'tickets' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
        ]);

        $this->actingAs($attendee)
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Bookings/Transaction')
                    ->where('event.id', $event->id)
                    ->has('selection', 1)
                    ->where('selection.0.quantity', 2)
                    ->where('total_amount', 200000)
            );
    }

    public function test_transaction_page_accepts_bracket_style_query(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);

        // The shape Inertia emits with the default `brackets` format.
        $url = route('bookings.create', $event->id)
            ."?tickets[][ticket_id]={$ticket->id}&tickets[][quantity]=2";

        $this->get($url)
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Bookings/Transaction')
                    ->has('selection', 1)
                    ->where('selection.0.quantity', 2)
            );
    }

    public function test_organizer_cannot_open_the_transaction_page(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $ticket = $this->ticket($event);

        $url = route('bookings.create', [
            'event' => $event->id,
            'tickets' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        ]);

        $this->actingAs($organizer)
            ->get($url)
            ->assertRedirect(route('events.show', $event->slug))
            ->assertSessionHas('error');
    }

    public function test_individual_attendee_names_are_stored_per_seat(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event, ['quantity' => 10]);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket, 2, 'individual', [
            ['name' => 'Andi', 'email' => 'andi@example.com'],
            ['name' => 'Bunga', 'email' => null],
        ]));

        $booking = Booking::firstOrFail();
        $rows = $booking->bookingTickets()->get();

        $this->assertCount(2, $rows);
        $this->assertSame(2, (int) $rows->sum('quantity'));
        $this->assertTrue($rows->every(fn ($row) => (int) $row->quantity === 1));
        $this->assertEqualsCanonicalizing(['Andi', 'Bunga'], $rows->pluck('attendee_name')->all());
        $this->assertCount(2, $rows->pluck('ticket_code')->unique());
        $this->assertSame(2, $ticket->fresh()->quantity_sold);
    }

    public function test_representative_name_applies_to_every_seat(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event, ['quantity' => 10]);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket, 3, 'representative'));

        $rows = Booking::firstOrFail()->bookingTickets()->get();

        $this->assertCount(3, $rows);
        $this->assertSame(['Budi Perwakilan'], $rows->pluck('attendee_name')->unique()->values()->all());
        $this->assertCount(3, $rows->pluck('ticket_code')->unique());
    }

    public function test_individual_mode_requires_matching_attendee_count(): void
    {
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event, ['quantity' => 10]);

        $this->actingAs($this->attendee())
            ->post(route('bookings.store', $event), $this->bookPayload($ticket, 2, 'individual', [
                ['name' => 'Only One'],
            ]))
            ->assertSessionHasErrors('tickets.0.attendees');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_received_email_is_queued_for_pending_booking(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);

        $this->actingAs($this->attendee())
            ->post(route('bookings.store', $event), $this->bookPayload($ticket));

        Mail::assertQueued(BookingReceivedNotification::class);
        Mail::assertNotQueued(BookingPaidNotification::class);
    }

    public function test_payment_success_email_with_qr_is_queued_after_payment(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket));
        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'qris']);

        Mail::assertQueued(BookingPaidNotification::class);
    }

    public function test_free_booking_sends_payment_success_email_immediately(): void
    {
        Mail::fake();
        $event = $this->event($this->organizer());
        $ticket = $this->ticket($event, ['price' => 0]);

        $this->actingAs($this->attendee())
            ->post(route('bookings.store', $event), $this->bookPayload($ticket));

        Mail::assertQueued(BookingPaidNotification::class);
        Mail::assertNotQueued(BookingReceivedNotification::class);
    }

    public function test_attendee_phone_is_stored_and_whatsapp_delivery_consumes_quota(): void
    {
        Mail::fake();
        $organizer = $this->organizer();
        $organizer->forceFill(['whatsapp_quota' => 5])->save();

        $event = $this->event($organizer, ['whatsapp_enabled' => true]);
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket, 1, 'individual', [
            ['name' => 'Andi', 'email' => 'andi@example.com', 'phone' => '081234567890'],
        ]));

        $booking = Booking::firstOrFail();
        $this->assertSame('081234567890', $booking->bookingTickets()->first()->attendee_phone);

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'qris']);

        $this->assertSame(4, $organizer->fresh()->whatsapp_quota);
    }

    public function test_whatsapp_delivery_is_skipped_when_event_disabled(): void
    {
        Mail::fake();
        $organizer = $this->organizer();
        $organizer->forceFill(['whatsapp_quota' => 5])->save();

        $event = $this->event($organizer); // whatsapp_enabled defaults false
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket, 1, 'individual', [
            ['name' => 'Andi', 'email' => 'andi@example.com', 'phone' => '081234567890'],
        ]));

        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'qris']);

        $this->assertSame(5, $organizer->fresh()->whatsapp_quota);
    }

    public function test_whatsapp_delivery_is_skipped_without_quota(): void
    {
        Mail::fake();
        $organizer = $this->organizer(); // quota 0

        $event = $this->event($organizer, ['whatsapp_enabled' => true]);
        $ticket = $this->ticket($event);
        $attendee = $this->attendee();

        $this->actingAs($attendee)->post(route('bookings.store', $event), $this->bookPayload($ticket, 1, 'individual', [
            ['name' => 'Andi', 'email' => 'andi@example.com', 'phone' => '081234567890'],
        ]));

        $booking = Booking::firstOrFail();

        $this->actingAs($attendee)
            ->post(route('bookings.pay.store', $booking), ['payment_method' => 'qris']);

        // Consume is refused at zero; balance never goes negative.
        $this->assertSame(0, $organizer->fresh()->whatsapp_quota);
    }
}
