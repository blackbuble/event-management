<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function publishedEvent(User $organizer, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'user_id' => $organizer->id,
            'title' => 'Indie Music Festival 2026',
            'description' => 'Three stages, one day.',
            'venue_name' => 'Balai Kartini',
            'venue_address' => 'Semarang',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10)->addHours(6),
            'type' => 'offline',
            'status' => 'published',
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_organizer_with_no_data_sees_zeroed_stats_and_empty_activity(): void
    {
        $this->actingAs($this->organizer())
            ->get(route('dashboard'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Dashboard')
                    ->where('canCreateEvent', true)
                    ->where('dashboard.view', 'organizer')
                    ->where('dashboard.stats.active_events', 0)
                    ->where('dashboard.stats.draft_events', 0)
                    ->where('dashboard.stats.tickets_sold', 0)
                    ->where('dashboard.stats.revenue', 0)
                    ->where('dashboard.recent_activity', [])
            );
    }

    public function test_organizer_sees_real_stats_from_own_events_only(): void
    {
        $organizer = $this->organizer();
        $otherOrganizer = $this->organizer();

        $activeEvent = $this->publishedEvent($organizer);
        $this->publishedEvent($organizer, ['title' => 'Draft Meetup', 'status' => 'draft']);
        $this->publishedEvent($otherOrganizer, ['title' => 'Someone Else Event']);

        $ticket = Ticket::create([
            'event_id' => $activeEvent->id,
            'name' => 'Regular',
            'price' => 150000,
            'quantity' => 100,
            'quantity_sold' => 40,
            'quantity_reserved' => 5,
        ]);

        $attendee = $this->attendee();
        $booking = Booking::create([
            'user_id' => $attendee->id,
            'event_id' => $activeEvent->id,
            'status' => 'confirmed',
            'total_amount' => 300000,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($organizer)
            ->get(route('dashboard'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Dashboard')
                    ->where('canCreateEvent', true)
                    ->where('dashboard.view', 'organizer')
                    ->where('dashboard.stats.active_events', 1)
                    ->where('dashboard.stats.draft_events', 1)
                    ->where('dashboard.stats.tickets_sold', 40)
                    ->where('dashboard.stats.tickets_reserved', 5)
                    ->where('dashboard.stats.revenue', 300000)
                    ->where('dashboard.stats.confirmed_bookings', 1)
                    ->has('dashboard.recent_activity', 1)
                    ->where('dashboard.recent_activity.0.event_title', 'Indie Music Festival 2026')
                    ->where('dashboard.recent_activity.0.attendee_name', $attendee->name)
                    ->where('dashboard.recent_activity.0.status', 'confirmed')
            );
    }

    public function test_past_events_do_not_count_as_active(): void
    {
        $organizer = $this->organizer();

        $this->publishedEvent($organizer, [
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(5)->addHours(6),
        ]);

        $this->actingAs($organizer)
            ->get(route('dashboard'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('dashboard.stats.active_events', 0)
                    ->where('dashboard.stats.revenue', 0)
                    ->where('dashboard.recent_activity', [])
            );
    }

    public function test_attendee_sees_personal_stats_and_no_create_button(): void
    {
        $organizer = $this->organizer();
        $attendee = $this->attendee();

        $event = $this->publishedEvent($organizer);
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 150000,
            'quantity' => 100,
        ]);

        $booking = Booking::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 300000,
            'payment_status' => 'paid',
        ]);

        BookingTicket::create([
            'booking_id' => $booking->id,
            'ticket_id' => $ticket->id,
            'ticket_code' => 'TKN-000001',
            'quantity' => 2,
            'price' => 150000,
        ]);

        $this->actingAs($attendee)
            ->get(route('dashboard'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Dashboard')
                    ->where('canCreateEvent', false)
                    ->where('dashboard.view', 'attendee')
                    ->where('dashboard.stats.upcoming_bookings', 1)
                    ->where('dashboard.stats.total_tickets', 2)
                    ->where('dashboard.stats.total_spent', 300000)
                    ->has('dashboard.recent_activity', 1)
                    ->where('dashboard.recent_activity.0.event_title', 'Indie Music Festival 2026')
                    ->where('dashboard.recent_activity.0.status', 'confirmed')
            );
    }

    public function test_cancelled_bookings_do_not_count_toward_attendee_tickets(): void
    {
        $organizer = $this->organizer();
        $attendee = $this->attendee();

        $event = $this->publishedEvent($organizer);
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 150000,
            'quantity' => 100,
        ]);

        $booking = Booking::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => 'cancelled',
            'total_amount' => 300000,
            'payment_status' => 'refunded',
        ]);

        BookingTicket::create([
            'booking_id' => $booking->id,
            'ticket_id' => $ticket->id,
            'ticket_code' => 'TKN-000002',
            'quantity' => 2,
            'price' => 150000,
        ]);

        $this->actingAs($attendee)
            ->get(route('dashboard'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('dashboard.stats.total_tickets', 0)
                    ->where('dashboard.stats.total_spent', 0)
                    ->where('dashboard.stats.upcoming_bookings', 0)
            );
    }
}
