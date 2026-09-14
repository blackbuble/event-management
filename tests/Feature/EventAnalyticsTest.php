<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventAnalyticsTest extends TestCase
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
            'title' => 'Analytics Fest',
            'description' => 'x',
            'venue_name' => 'V',
            'venue_address' => 'A',
            'type' => 'offline',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(3),
            'status' => 'published',
            'capacity' => 500,
        ], $overrides));
    }

    private function seedSales(Event $event): void
    {
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 100000,
            'quantity' => 100,
            'quantity_sold' => 2,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
        ]);

        $paid = Booking::create([
            'user_id' => $this->attendee()->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 200000,
            'payment_status' => 'paid',
        ]);
        $paid->bookingTickets()->create([
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'price' => 100000,
            'checked_in' => true,
        ]);

        $cancelled = Booking::create([
            'user_id' => $this->attendee()->id,
            'event_id' => $event->id,
            'status' => 'cancelled',
            'total_amount' => 100000,
            'payment_status' => 'unpaid',
        ]);
        $cancelled->bookingTickets()->create([
            'ticket_id' => $ticket->id,
            'quantity' => 1,
            'price' => 100000,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $event = $this->event($this->organizer());

        $this->get(route('events.analytics', $event))->assertRedirect(route('login'));
    }

    public function test_owner_can_view_event_analytics(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $this->seedSales($event);

        $this->actingAs($organizer)
            ->get(route('events.analytics', $event))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Events/Analytics')
                    ->where('analytics.event.id', $event->id)
                    ->where('analytics.summary.revenue', 200000)
                    ->where('analytics.summary.tickets_sold', 2)
                    ->where('analytics.summary.confirmed_bookings', 1)
                    ->where('analytics.summary.cancelled_bookings', 1)
                    ->where('analytics.summary.checked_in', 2)
                    ->where('analytics.summary.tickets_available', 98)
                    ->where('analytics.summary.sell_through', 2)
                    ->has('analytics.tickets', 1)
                    ->where('analytics.tickets.0.sold', 2)
                    ->where('analytics.tickets.0.remaining', 98)
                    ->where('analytics.tickets.0.revenue', 200000)
                    ->has('analytics.timeline', 14)
            );
    }

    public function test_non_owner_organizer_cannot_view_analytics(): void
    {
        $event = $this->event($this->organizer());

        $this->actingAs($this->organizer())
            ->get(route('events.analytics', $event))
            ->assertForbidden();
    }

    public function test_attendee_cannot_view_analytics(): void
    {
        $event = $this->event($this->organizer());

        $this->actingAs($this->attendee())
            ->get(route('events.analytics', $event))
            ->assertForbidden();
    }
}
