<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventReview;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventReviewTest extends TestCase
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
            'title' => 'Past Community Meetup',
            'description' => 'Already happened.',
            'venue_name' => 'Co-working Space',
            'venue_address' => 'Bandung',
            'type' => 'offline',
            'start_date' => now()->subDays(7),
            'end_date' => now()->subDays(7)->addHours(3),
            'status' => 'published',
        ], $overrides));
    }

    private function confirmBooking(User $attendee, Event $event): Booking
    {
        return Booking::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 0,
            'payment_status' => 'free',
        ]);
    }

    public function test_guest_cannot_submit_review(): void
    {
        $event = $this->event($this->organizer());

        $this->post(route('events.reviews.store', $event), ['rating' => 5])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_attendee_with_confirmed_booking_can_review_finished_event(): void
    {
        $event = $this->event($this->organizer());
        $attendee = $this->attendee();
        $this->confirmBooking($attendee, $event);

        $this->actingAs($attendee)
            ->post(route('events.reviews.store', $event), [
                'rating' => 5,
                'comment' => 'Great session!',
            ])
            ->assertRedirect()
            ->assertSessionHas('message');

        $this->assertDatabaseHas('event_reviews', [
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'rating' => 5,
            'comment' => 'Great session!',
        ]);
    }

    public function test_user_without_confirmed_booking_cannot_review(): void
    {
        $event = $this->event($this->organizer());

        $this->actingAs($this->attendee())
            ->post(route('events.reviews.store', $event), ['rating' => 4])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_event_owner_cannot_review_own_event(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $this->confirmBooking($organizer, $event);

        $this->actingAs($organizer)
            ->post(route('events.reviews.store', $event), ['rating' => 5])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_upcoming_event_cannot_be_reviewed(): void
    {
        $event = $this->event($this->organizer(), [
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $attendee = $this->attendee();
        $this->confirmBooking($attendee, $event);

        $this->actingAs($attendee)
            ->post(route('events.reviews.store', $event), ['rating' => 5])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_cancelled_event_cannot_be_reviewed(): void
    {
        $event = $this->event($this->organizer(), ['status' => 'cancelled']);
        $attendee = $this->attendee();
        $this->confirmBooking($attendee, $event);

        $this->actingAs($attendee)
            ->post(route('events.reviews.store', $event), ['rating' => 5])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_duplicate_review_is_rejected(): void
    {
        $event = $this->event($this->organizer());
        $attendee = $this->attendee();
        $this->confirmBooking($attendee, $event);

        $this->actingAs($attendee)->post(route('events.reviews.store', $event), ['rating' => 5]);
        $this->actingAs($attendee)->post(route('events.reviews.store', $event), ['rating' => 3])
            ->assertSessionHas('error');

        $this->assertSame(1, EventReview::where('event_id', $event->id)->count());
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $event = $this->event($this->organizer());
        $attendee = $this->attendee();
        $this->confirmBooking($attendee, $event);

        $this->actingAs($attendee)
            ->post(route('events.reviews.store', $event), ['rating' => 0])
            ->assertSessionHasErrors('rating');

        $this->actingAs($attendee)
            ->post(route('events.reviews.store', $event), ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->actingAs($attendee)
            ->post(route('events.reviews.store', $event), ['rating' => 'five'])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_landing_exposes_review_eligibility_and_own_review(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $attendee = $this->attendee();
        $this->confirmBooking($attendee, $event);

        $this->actingAs($attendee)
            ->get(route('events.show', $event->slug))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('event.can_review', true)
                    ->where('event.my_review', null)
            );

        $this->actingAs($attendee)->post(route('events.reviews.store', $event), ['rating' => 4, 'comment' => 'Nice']);

        $this->actingAs($attendee)
            ->get(route('events.show', $event->slug))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('event.can_review', false)
                    ->where('event.my_review.rating', 4)
                    ->where('event.my_review.comment', 'Nice')
            );
    }

    public function test_landing_exposes_organizer_rating_aggregate(): void
    {
        $organizer = $this->organizer();
        $first = $this->event($organizer, ['title' => 'First Run']);
        $second = $this->event($organizer, [
            'title' => 'Second Run',
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(30)->addHours(2),
        ]);

        EventReview::create(['event_id' => $first->id, 'user_id' => $this->attendee()->id, 'rating' => 5]);
        EventReview::create(['event_id' => $second->id, 'user_id' => $this->attendee()->id, 'rating' => 3]);

        $this->get(route('events.show', $first->slug))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('event.organizer.rating.average', 4)
                    ->where('event.organizer.rating.count', 2)
            );
    }

    public function test_landing_exposes_past_events_excluding_current(): void
    {
        $organizer = $this->organizer();
        $pastDone = $this->event($organizer, [
            'title' => 'Older Meetup',
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(60)->addHours(2),
        ]);
        $current = $this->event($organizer, ['title' => 'Current Meetup']);
        $future = $this->event($organizer, [
            'title' => 'Future Meetup',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10)->addHours(2),
        ]);

        $this->get(route('events.show', $current->slug))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('event.organizer.past_events', 1)
                    ->where('event.organizer.past_events.0.title', 'Older Meetup')
                    ->where('event.organizer.past_events.0.slug', $pastDone->slug)
            );
    }

    public function test_landing_exposes_registered_count_and_initial_avatars_without_full_names(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $first = User::factory()->create(['name' => 'Budi Santoso', 'phone' => fake()->unique()->numerify('+62811#########')]);
        $this->confirmBooking($first, $event);
        $second = User::factory()->create(['name' => 'Sinta Dewi', 'phone' => fake()->unique()->numerify('+62812#########')]);
        $this->confirmBooking($second, $event);
        // pending booking must not count
        $pending = $this->attendee();
        Booking::create([
            'user_id' => $pending->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'total_amount' => 0,
            'payment_status' => 'unpaid',
        ]);

        $this->get(route('events.show', $event->slug))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('event.attendees.count', 2)
                    ->has('event.attendees.avatars', 2)
                    ->where('event.attendees.avatars.0.initials', 'BS')
                    ->where('event.attendees.avatars.1.initials', 'SD')
                    ->missing('event.attendees.avatars.0.name')
            );
    }

    public function test_landing_dates_are_rendered_in_the_app_timezone(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);

        $organizer = $this->organizer();
        $event = $this->event($organizer, [
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(3),
        ]);

        $this->get(route('events.show', $event->slug))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('event.start_date', fn ($value) => is_string($value) && str_contains($value, '+07:00'))
            );
    }
}
