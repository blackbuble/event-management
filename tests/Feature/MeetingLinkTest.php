<?php

namespace Tests\Feature;

use App\Mail\MeetingLinkNotification;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MeetingLinkTest extends TestCase
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

    private function attendee(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'phone' => fake()->unique()->numerify('+62898#########'),
        ], $overrides));
        $user->assignRole('attendee');

        return $user;
    }

    private function onlineEvent(User $organizer, ?string $meetingLink = null): Event
    {
        return Event::create([
            'user_id' => $organizer->id,
            'title' => 'Webinar Laravel Advanced',
            'description' => 'Deep dive into advanced Laravel patterns.',
            'venue_name' => 'Online Event',
            'venue_address' => 'Online',
            'type' => 'online',
            'meeting_link' => $meetingLink,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10)->addHours(3),
            'status' => 'published',
        ]);
    }

    private function confirmedBooking(User $attendee, Event $event): Booking
    {
        return Booking::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 0,
            'payment_status' => 'free',
        ]);
    }

    public function test_guest_cannot_access_meeting_link_endpoints(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer);

        $this->patch(route('events.meeting-link.update', $event), ['meeting_link' => 'https://zoom.us/j/1'])
            ->assertRedirect(route('login'));

        $this->post(route('events.meeting-link.send', $event))
            ->assertRedirect(route('login'));
    }

    public function test_organizer_can_view_my_events_page(): void
    {
        $organizer = $this->organizer();
        $this->onlineEvent($organizer, 'https://zoom.us/j/123');

        $this->actingAs($organizer)
            ->get(route('events.index'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Events/Index')
                    ->has('events', 1)
                    ->where('events.0.title', 'Webinar Laravel Advanced')
                    ->where('events.0.meeting_link', 'https://zoom.us/j/123')
                    ->where('events.0.confirmed_bookings', 0)
            );
    }

    public function test_my_events_page_only_lists_own_events(): void
    {
        $organizer = $this->organizer();
        $other = $this->organizer();

        $this->onlineEvent($organizer);
        $this->onlineEvent($other);
        $this->onlineEvent($other);

        $this->actingAs($organizer)
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1));
    }

    public function test_owner_can_set_meeting_link_after_creation(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer);

        $this->actingAs($organizer)
            ->patch(route('events.meeting-link.update', $event), [
                'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            ])
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('message');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);
    }

    public function test_non_owner_organizer_cannot_set_meeting_link(): void
    {
        $owner = $this->organizer();
        $event = $this->onlineEvent($owner);

        $this->actingAs($this->organizer())
            ->patch(route('events.meeting-link.update', $event), [
                'meeting_link' => 'https://zoom.us/j/999',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'meeting_link' => null,
        ]);
    }

    public function test_attendee_cannot_set_or_send_meeting_link(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer, 'https://zoom.us/j/1');

        $this->actingAs($this->attendee())
            ->patch(route('events.meeting-link.update', $event), [
                'meeting_link' => 'https://evil.example.com/join',
            ])
            ->assertForbidden();

        $this->actingAs($this->attendee())
            ->post(route('events.meeting-link.send', $event))
            ->assertForbidden();
    }

    public function test_meeting_link_must_be_a_valid_url(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer);

        $this->actingAs($organizer)
            ->patch(route('events.meeting-link.update', $event), [
                'meeting_link' => 'not-a-url',
            ])
            ->assertSessionHasErrors('meeting_link');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'meeting_link' => null,
        ]);
    }

    public function test_send_requires_meeting_link_to_be_set(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer);

        Mail::fake();

        $this->actingAs($organizer)
            ->post(route('events.meeting-link.send', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    public function test_send_delivers_link_to_confirmed_attendees_only(): void
    {
        Mail::fake();

        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer, 'https://zoom.us/j/42');

        $emailAndPhoneUser = $this->attendee();
        $phoneOnlyUser = $this->attendee(['email' => null, 'email_verified_at' => null]);
        $pendingUser = $this->attendee();
        $otherEventAttendee = $this->attendee();
        $otherEvent = $this->onlineEvent($this->organizer(), 'https://zoom.us/j/99');

        $this->confirmedBooking($emailAndPhoneUser, $event);
        $this->confirmedBooking($phoneOnlyUser, $event);
        $this->confirmedBooking($pendingUser, $event)->update(['status' => 'pending']);
        $this->confirmedBooking($otherEventAttendee, $otherEvent);

        $response = $this->actingAs($organizer)
            ->post(route('events.meeting-link.send', $event));

        $response->assertRedirect(route('events.index'))->assertSessionHas('message');

        // Only users with an email on file get the queued mailable
        Mail::assertQueued(MeetingLinkNotification::class, 1);
        Mail::assertQueued(
            MeetingLinkNotification::class,
            fn (MeetingLinkNotification $mail) => $mail->recipient->is($emailAndPhoneUser)
                && $mail->hasTo($emailAndPhoneUser->email)
        );
    }

    public function test_send_is_idempotent_per_user_not_per_booking(): void
    {
        Mail::fake();

        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer, 'https://zoom.us/j/42');
        $attendee = $this->attendee();

        $this->confirmedBooking($attendee, $event);
        $this->confirmedBooking($attendee, $event);

        $this->actingAs($organizer)
            ->post(route('events.meeting-link.send', $event))
            ->assertRedirect(route('events.index'));

        Mail::assertQueued(MeetingLinkNotification::class, 1);
    }

    public function test_send_route_is_rate_limited(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer, 'https://zoom.us/j/42');

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($organizer)
                ->post(route('events.meeting-link.send', $event))
                ->assertRedirect();
        }

        $this->actingAs($organizer)
            ->post(route('events.meeting-link.send', $event))
            ->assertStatus(429);
    }

    public function test_meeting_link_cannot_be_set_on_offline_event(): void
    {
        $organizer = $this->organizer();
        $event = Event::create([
            'user_id' => $organizer->id,
            'title' => 'Offline Meetup',
            'description' => 'In person only.',
            'venue_name' => 'Cafe X',
            'venue_address' => 'Jakarta',
            'type' => 'offline',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(3),
            'status' => 'published',
        ]);

        $this->actingAs($organizer)
            ->patch(route('events.meeting-link.update', $event), [
                'meeting_link' => 'https://zoom.us/j/123',
            ])
            ->assertSessionHasErrors('meeting_link');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'meeting_link' => null,
        ]);
    }

    public function test_meeting_link_update_route_is_rate_limited(): void
    {
        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer);

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($organizer)
                ->patch(route('events.meeting-link.update', $event), [
                    'meeting_link' => 'https://zoom.us/j/'.$i,
                ])
                ->assertRedirect();
        }

        $this->actingAs($organizer)
            ->patch(route('events.meeting-link.update', $event), [
                'meeting_link' => 'https://zoom.us/j/999',
            ])
            ->assertStatus(429);
    }

    public function test_link_cannot_be_sent_for_cancelled_event(): void
    {
        Mail::fake();

        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer, 'https://zoom.us/j/42');
        $event->update(['status' => 'cancelled']);

        $this->confirmedBooking($this->attendee(), $event);

        $this->actingAs($organizer)
            ->post(route('events.meeting-link.send', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    public function test_link_cannot_be_sent_for_ended_event(): void
    {
        Mail::fake();

        $organizer = $this->organizer();
        $event = $this->onlineEvent($organizer, 'https://zoom.us/j/42');
        $event->update([
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDays(2)->addHours(3),
        ]);

        $this->confirmedBooking($this->attendee(), $event);

        $this->actingAs($organizer)
            ->post(route('events.meeting-link.send', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    public function test_my_events_list_is_paginated(): void
    {
        $organizer = $this->organizer();

        for ($i = 1; $i <= 12; $i++) {
            $this->onlineEvent($organizer)->update(['title' => "Webinar Edition {$i}"]);
        }

        $this->actingAs($organizer)
            ->get(route('events.index'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Events/Index')
                    ->has('events', 10)
                    ->where('pagination.current_page', 1)
                    ->where('pagination.last_page', 2)
                    ->where('pagination.total', 12)
            );

        $this->actingAs($organizer)
            ->get(route('events.index', ['page' => 2]))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('events', 2)
                    ->where('pagination.current_page', 2)
            );
    }
}
