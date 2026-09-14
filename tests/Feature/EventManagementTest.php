<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EventService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, CategorySeeder::class]);
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
            'description' => 'Monthly community gathering.',
            'venue_name' => 'Co-working Space',
            'venue_address' => 'Bandung',
            'type' => 'offline', 'category' => 'music',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(3),
            'status' => 'draft',
        ], $overrides));
    }

    public function test_published_event_landing_is_publicly_visible(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, [
            'status' => 'published',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 50000,
            'quantity' => 100,
            'quantity_sold' => 20,
        ]);

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Events/Show')
                    ->where('is_owner', false)
                    ->where('event.title', 'Community Meetup')
                    ->where('event.slug', 'community-meetup')
                    ->where('event.status', 'published')
                    ->where('event.latitude', -6.2)
                    ->where('event.longitude', 106.8)
                    ->where('event.organizer.id', $organizer->id)
                    ->where('event.organizer.name', $organizer->name)
                    ->has('event.tickets', 1)
                    ->where('event.tickets.0.name', 'Regular')
                    ->where('event.tickets.0.price', 50000)
                    ->where('event.tickets.0.remaining', 80)
            );
    }

    public function test_draft_event_landing_is_hidden_from_guests_but_visible_to_owner(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer); // draft

        $this->get(route('events.show', $event->slug))->assertNotFound();

        $this->actingAs($organizer)
            ->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('is_owner', true));
    }

    public function test_cancelled_event_landing_is_hidden_from_other_users(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, ['status' => 'cancelled']);

        $this->actingAs($this->attendee())
            ->get(route('events.show', $event->slug))
            ->assertNotFound();

        $this->actingAs($organizer)
            ->get(route('events.show', $event->slug))
            ->assertOk();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get(route('events.show', 'does-not-exist'))->assertNotFound();
    }

    public function test_owner_can_view_edit_page(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->actingAs($organizer)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Events/Edit')
                    ->where('event.title', 'Community Meetup')
                    ->where('event.status', 'draft')
            );
    }

    public function test_non_owner_and_attendee_cannot_edit(): void
    {
        $owner = $this->organizer();
        $event = $this->event($owner);

        $this->actingAs($this->organizer())
            ->get(route('events.edit', $event))
            ->assertForbidden();

        $this->actingAs($this->attendee())
            ->get(route('events.edit', $event))
            ->assertForbidden();
    }

    public function test_owner_can_update_event_and_slug_stays_untouched(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, ['status' => 'published']);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), [
                'title' => 'Community Meetup v2',
                'description' => 'Updated description.',
                'type' => 'offline', 'category' => 'music',
                'venue_name' => 'New Venue',
                'venue_address' => 'Jakarta',
                'start_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(9)->addHours(4)->format('Y-m-d\TH:i'),
                'capacity' => '50',
                'status' => 'published',
                'slug' => 'attempted-slug-hijack',
            ])
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Community Meetup v2',
            'venue_name' => 'New Venue',
            'capacity' => 50,
            'slug' => 'community-meetup',
        ]);
    }

    public function test_update_replaces_banner_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $event->update(['image' => 'events/old-banner.jpg']);
        Storage::disk('public')->put('events/old-banner.jpg', 'old');

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), [
                'title' => 'Community Meetup',
                'description' => 'Updated description.',
                'type' => 'offline', 'category' => 'music',
                'venue_name' => 'Co-working Space',
                'venue_address' => 'Bandung',
                'start_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(9)->addHours(4)->format('Y-m-d\TH:i'),
                'status' => 'draft',
                'image' => UploadedFile::fake()->image('new-banner.jpg'),
            ])
            ->assertRedirect(route('events.index'));

        Storage::disk('public')->assertMissing('events/old-banner.jpg');
        $this->assertStringStartsWith('events/', $event->fresh()->image);
        Storage::disk('public')->assertExists($event->fresh()->image);
    }

    public function test_update_without_image_keeps_existing_banner(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, ['image' => 'events/keep-me.jpg']);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), [
                'title' => 'Community Meetup',
                'description' => 'Updated description.',
                'type' => 'offline', 'category' => 'music',
                'venue_name' => 'Co-working Space',
                'venue_address' => 'Bandung',
                'start_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(9)->addHours(4)->format('Y-m-d\TH:i'),
                'status' => 'draft',
            ])
            ->assertRedirect(route('events.index'));

        $this->assertSame('events/keep-me.jpg', $event->fresh()->image);
    }

    public function test_update_validation_requires_end_after_start(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), [
                'title' => 'X',
                'description' => 'Y',
                'type' => 'offline', 'category' => 'music',
                'venue_name' => 'V',
                'venue_address' => 'A',
                'start_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(9)->subHours(1)->format('Y-m-d\TH:i'),
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_draft_event_can_be_published(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->actingAs($organizer)
            ->patch(route('events.publish', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('message');

        $this->assertSame('published', $event->fresh()->status);
    }

    public function test_already_published_event_cannot_be_published_again(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, ['status' => 'published']);

        $this->actingAs($organizer)
            ->patch(route('events.publish', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error');

        $this->assertSame('published', $event->fresh()->status);
    }

    public function test_published_event_can_be_cancelled(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, ['status' => 'published']);

        $this->actingAs($organizer)
            ->patch(route('events.cancel', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('message');

        $this->assertSame('cancelled', $event->fresh()->status);
    }

    public function test_cancelled_event_cannot_be_cancelled_again(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, ['status' => 'cancelled']);

        $this->actingAs($organizer)
            ->patch(route('events.cancel', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error');
    }

    public function test_non_owner_cannot_publish_or_cancel(): void
    {
        $owner = $this->organizer();
        $event = $this->event($owner);

        $other = $this->organizer();
        $this->actingAs($other)->patch(route('events.publish', $event))->assertForbidden();
        $this->actingAs($other)->patch(route('events.cancel', $event))->assertForbidden();
    }

    public function test_owner_can_delete_event_without_bookings(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->actingAs($organizer)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('message');

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_event_with_active_bookings_cannot_be_deleted(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        Booking::create([
            'user_id' => $this->attendee()->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 0,
            'payment_status' => 'free',
        ]);

        $this->actingAs($organizer)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_dashboard_event_actions_require_authentication(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->get(route('events.edit', $event))->assertRedirect(route('login'));
        $this->patch(route('events.update', $event), [])->assertRedirect(route('login'));
        $this->patch(route('events.publish', $event))->assertRedirect(route('login'));
        $this->patch(route('events.cancel', $event))->assertRedirect(route('login'));
        $this->delete(route('events.destroy', $event))->assertRedirect(route('login'));
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Community Meetup',
            'description' => 'Updated description.',
            'type' => 'offline', 'category' => 'music',
            'venue_name' => 'Co-working Space',
            'venue_address' => 'Bandung',
            'start_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(9)->addHours(4)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ], $overrides);
    }

    private function ticket(Event $event, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 100000,
            'quantity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
        ], $overrides));
    }

    public function test_update_reconciles_ticket_types(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $existing = $this->ticket($event);
        $removed = $this->ticket($event, ['name' => 'Legacy']);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), $this->updatePayload([
                'tickets' => [
                    [
                        'id' => $existing->id,
                        'name' => 'Regular v2',
                        'price' => '120000',
                        'quantity' => '80',
                        'min_per_order' => '1',
                        'max_per_order' => '4',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'VIP',
                        'price' => '500000',
                        'quantity' => '20',
                        'min_per_order' => '1',
                        'max_per_order' => '2',
                        'is_active' => true,
                    ],
                ],
            ]))
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('tickets', [
            'id' => $existing->id,
            'name' => 'Regular v2',
            'price' => 120000,
            'quantity' => 80,
        ]);
        $this->assertDatabaseHas('tickets', ['event_id' => $event->id, 'name' => 'VIP']);
        $this->assertDatabaseMissing('tickets', ['id' => $removed->id]);
        $this->assertSame(2, $event->tickets()->count());
    }

    public function test_update_can_toggle_whatsapp_delivery(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $this->assertFalse((bool) $event->fresh()->whatsapp_enabled);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), $this->updatePayload(['whatsapp_enabled' => true]))
            ->assertRedirect(route('events.index'));

        $this->assertTrue($event->fresh()->whatsapp_enabled);
    }

    public function test_update_can_change_category(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), $this->updatePayload(['category' => 'technology']))
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', ['id' => $event->id, 'category' => 'technology']);
    }

    public function test_ticket_update_guards_against_stale_allocated_count(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $ticket = $this->ticket($event, ['quantity' => 100]);

        // Simulate a concurrent booking incrementing sold after the model was loaded.
        Ticket::whereKey($ticket->id)->update(['quantity_sold' => 60]);

        // The in-memory model still reports sold = 0, but the lock re-reads 60.
        $this->expectException(\InvalidArgumentException::class);
        app(EventService::class)->updateTicket($ticket, ['quantity' => 40]);
    }

    public function test_update_rejects_ticket_quantity_below_allocated(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $ticket = $this->ticket($event, ['quantity' => 100, 'quantity_sold' => 60]);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), $this->updatePayload([
                'tickets' => [
                    [
                        'id' => $ticket->id,
                        'name' => 'Regular',
                        'price' => '100000',
                        'quantity' => '40',
                        'min_per_order' => '1',
                        'max_per_order' => '5',
                        'is_active' => true,
                    ],
                ],
            ]))
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHas('error');

        $this->assertSame(100, $ticket->fresh()->quantity);
    }

    public function test_update_cannot_remove_ticket_with_active_bookings(): void
    {
        $organizer = $this->organizer();
        $attendee = $this->attendee();
        $event = $this->event($organizer);
        $booked = $this->ticket($event);

        $booking = Booking::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 100000,
            'payment_status' => 'paid',
        ]);
        $booking->bookingTickets()->create([
            'ticket_id' => $booked->id,
            'quantity' => 1,
            'price' => 100000,
        ]);

        $this->actingAs($organizer)
            ->patch(route('events.update', $event), $this->updatePayload([
                'tickets' => [
                    [
                        'name' => 'Replacement',
                        'price' => '100000',
                        'quantity' => '50',
                        'min_per_order' => '1',
                        'max_per_order' => '5',
                        'is_active' => true,
                    ],
                ],
            ]))
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('tickets', ['id' => $booked->id]);
    }

    public function test_owner_can_add_ticket_via_endpoint(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);

        $this->actingAs($organizer)
            ->post(route('events.tickets.store', $event), [
                'name' => 'Early Bird',
                'price' => '75000',
                'quantity' => '30',
                'min_per_order' => '1',
                'max_per_order' => '3',
                'is_active' => true,
            ])
            ->assertRedirect(route('events.edit', $event));

        $this->assertDatabaseHas('tickets', [
            'event_id' => $event->id,
            'name' => 'Early Bird',
            'price' => 75000,
            'quantity' => 30,
        ]);
    }

    public function test_non_owner_cannot_add_ticket(): void
    {
        $event = $this->event($this->organizer());

        $this->actingAs($this->organizer())
            ->post(route('events.tickets.store', $event), [
                'name' => 'Hijack',
                'price' => '1',
                'quantity' => '1',
                'min_per_order' => '1',
                'max_per_order' => '1',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_update_and_delete_ticket_via_endpoint(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $ticket = $this->ticket($event);

        $this->actingAs($organizer)
            ->patch(route('events.tickets.update', [$event, $ticket]), [
                'name' => 'Regular Updated',
                'price' => '150000',
                'quantity' => '90',
                'min_per_order' => '1',
                'max_per_order' => '4',
                'is_active' => true,
            ])
            ->assertRedirect(route('events.edit', $event));

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'name' => 'Regular Updated', 'price' => 150000]);

        $this->actingAs($organizer)
            ->delete(route('events.tickets.destroy', [$event, $ticket]))
            ->assertRedirect(route('events.edit', $event));

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    public function test_ticket_from_another_event_is_not_found(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $otherEvent = $this->event($organizer, ['title' => 'Other Event']);
        $foreignTicket = $this->ticket($otherEvent);

        $this->actingAs($organizer)
            ->delete(route('events.tickets.destroy', [$event, $foreignTicket]))
            ->assertNotFound();
    }

    public function test_guest_cannot_manage_tickets(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $ticket = $this->ticket($event);

        $this->post(route('events.tickets.store', $event), [])->assertRedirect(route('login'));
        $this->patch(route('events.tickets.update', [$event, $ticket]), [])->assertRedirect(route('login'));
        $this->delete(route('events.tickets.destroy', [$event, $ticket]))->assertRedirect(route('login'));
    }
}
