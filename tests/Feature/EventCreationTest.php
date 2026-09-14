<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventCreationTest extends TestCase
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

    private function validEventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Indie Music Festival 2026',
            'description' => 'A full day of independent bands across three stages.',
            'type' => 'offline',
            'category' => 'music',
            'venue_name' => 'Balai Kartini',
            'venue_address' => 'Jl. Gatot Subroto No. 27, Semarang',
            'latitude' => '-6.966667',
            'longitude' => '110.416664',
            'start_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(7)->addHours(6)->format('Y-m-d\TH:i'),
            'capacity' => '500',
            'status' => 'draft',
            'tickets' => [
                [
                    'name' => 'Regular',
                    'description' => 'General admission.',
                    'price' => '150000',
                    'quantity' => '100',
                    'min_per_order' => '1',
                    'max_per_order' => '5',
                    'is_active' => true,
                ],
            ],
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login_from_create_page(): void
    {
        $this->get(route('events.create'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_store_event(): void
    {
        $this->post(route('events.store'), $this->validEventData())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('events', 0);
    }

    public function test_attendee_cannot_access_create_page(): void
    {
        $this->actingAs($this->attendee())
            ->get(route('events.create'))
            ->assertForbidden();
    }

    public function test_attendee_cannot_store_event(): void
    {
        $this->actingAs($this->attendee())
            ->post(route('events.store'), $this->validEventData())
            ->assertForbidden();

        $this->assertDatabaseCount('events', 0);
    }

    public function test_user_with_incomplete_profile_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $user->assignRole('organizer');

        $this->actingAs($user)
            ->get(route('events.create'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_organizer_can_view_create_event_page(): void
    {
        $this->actingAs($this->organizer())
            ->get(route('events.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Events/Create'));
    }

    public function test_organizer_can_create_draft_offline_event(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)
            ->post(route('events.store'), $this->validEventData(['status' => 'draft']));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('message');

        $this->assertDatabaseHas('events', [
            'user_id' => $organizer->id,
            'title' => 'Indie Music Festival 2026',
            'slug' => 'indie-music-festival-2026',
            'venue_name' => 'Balai Kartini',
            'status' => 'draft',
            'capacity' => 500,
        ]);
    }

    public function test_ticket_settings_are_persisted_with_the_event(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData([
            'tickets' => [
                [
                    'name' => 'Early Bird',
                    'description' => 'Limited first release.',
                    'price' => '99000',
                    'quantity' => '50',
                    'sale_starts' => now()->addDay()->format('Y-m-d\TH:i'),
                    'sale_ends' => now()->addDays(3)->format('Y-m-d\TH:i'),
                    'min_per_order' => '1',
                    'max_per_order' => '4',
                    'is_active' => true,
                ],
                [
                    'name' => 'Regular',
                    'price' => '0',
                    'quantity' => '200',
                    'min_per_order' => '1',
                    'max_per_order' => '10',
                    'is_active' => false,
                ],
            ],
        ]))->assertRedirect(route('dashboard'));

        $event = $organizer->events()->first();

        $this->assertNotNull($event);
        $this->assertSame(2, $event->tickets()->count());

        $this->assertDatabaseHas('tickets', [
            'event_id' => $event->id,
            'name' => 'Early Bird',
            'price' => 99000,
            'quantity' => 50,
            'min_per_order' => 1,
            'max_per_order' => 4,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tickets', [
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 0,
            'quantity' => 200,
            'is_active' => false,
        ]);
    }

    public function test_event_category_is_required_and_persisted(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData([
            'category' => 'technology',
        ]))->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('events', [
            'user_id' => $organizer->id,
            'category' => 'technology',
        ]);

        $this->actingAs($organizer)
            ->post(route('events.store'), $this->validEventData([
                'title' => 'Missing Category',
                'category' => null,
                'start_date' => now()->addDays(20)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(20)->addHours(3)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('category');
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData(['category' => 'gaming']))
            ->assertSessionHasErrors('category');
    }

    public function test_whatsapp_delivery_toggle_is_persisted(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData([
            'whatsapp_enabled' => true,
        ]))->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('events', [
            'user_id' => $organizer->id,
            'whatsapp_enabled' => true,
        ]);
    }

    public function test_event_requires_at_least_one_ticket(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData(['tickets' => []]))
            ->assertSessionHasErrors('tickets');

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_row_validation_rejects_invalid_values(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'tickets' => [
                    ['name' => '', 'price' => '-10', 'quantity' => '0', 'min_per_order' => '1', 'max_per_order' => '10'],
                ],
            ]))
            ->assertSessionHasErrors([
                'tickets.0.name',
                'tickets.0.price',
                'tickets.0.quantity',
            ]);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_ticket_max_per_order_must_be_gte_min_per_order(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'tickets' => [
                    ['name' => 'VIP', 'price' => '500000', 'quantity' => '10', 'min_per_order' => '5', 'max_per_order' => '2'],
                ],
            ]))
            ->assertSessionHasErrors('tickets.0.max_per_order');
    }

    public function test_ticket_sale_window_must_end_after_it_starts(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'tickets' => [
                    [
                        'name' => 'Regular',
                        'price' => '100000',
                        'quantity' => '100',
                        'sale_starts' => now()->addDays(5)->format('Y-m-d\TH:i'),
                        'sale_ends' => now()->addDays(2)->format('Y-m-d\TH:i'),
                        'min_per_order' => '1',
                        'max_per_order' => '5',
                    ],
                ],
            ]))
            ->assertSessionHasErrors('tickets.0.sale_ends');
    }

    public function test_identical_double_submit_does_not_duplicate_tickets(): void
    {
        $organizer = $this->organizer();
        $payload = $this->validEventData();

        $this->actingAs($organizer)->post(route('events.store'), $payload);
        $this->actingAs($organizer)->post(route('events.store'), $payload);

        $this->assertSame(1, $organizer->events()->count());
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_organizer_can_create_online_event_and_venue_defaults_are_applied(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData([
            'type' => 'online',
            'venue_name' => null,
            'venue_address' => null,
            'latitude' => null,
            'longitude' => null,
            'meeting_link' => 'https://zoom.us/j/1234567890',
            'status' => 'published',
        ]));

        $this->assertDatabaseHas('events', [
            'user_id' => $organizer->id,
            'type' => 'online',
            'meeting_link' => 'https://zoom.us/j/1234567890',
            'venue_name' => 'Online Event',
            'venue_address' => 'https://zoom.us/j/1234567890',
            'status' => 'published',
        ]);
    }

    public function test_event_banner_is_stored_on_public_disk(): void
    {
        Storage::fake('public');
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData([
            'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        ]));

        $event = $organizer->events()->first();

        $this->assertNotNull($event->image);
        Storage::disk('public')->assertExists($event->image);
        $this->assertStringStartsWith('events/', $event->image);
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), [])
            ->assertSessionHasErrors([
                'title', 'description', 'type', 'start_date', 'end_date', 'status',
            ]);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_validation_fails_when_end_date_is_not_after_start_date(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'start_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(7)->subHours(2)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_validation_fails_when_start_date_is_in_the_past(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'start_date' => now()->subDays(3)->format('Y-m-d\TH:i'),
                'end_date' => now()->subDays(3)->addHours(4)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('start_date');
    }

    public function test_online_and_hybrid_events_can_be_created_without_meeting_link(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post(route('events.store'), $this->validEventData([
                'type' => 'online',
                'venue_name' => null,
                'venue_address' => null,
                'latitude' => null,
                'longitude' => null,
                'meeting_link' => null,
            ]))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($organizer)
            ->post(route('events.store'), $this->validEventData([
                'type' => 'hybrid',
                'meeting_link' => null,
                'start_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(9)->addHours(4)->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(2, $organizer->events()->count());
        $this->assertEquals(0, $organizer->events()->whereNotNull('meeting_link')->count());
    }

    public function test_invalid_meeting_link_format_is_rejected(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'type' => 'online',
                'venue_name' => null,
                'venue_address' => null,
                'meeting_link' => 'not-a-valid-url',
            ]))
            ->assertSessionHasErrors('meeting_link');
    }

    public function test_offline_event_requires_venue_fields(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData([
                'venue_name' => null,
                'venue_address' => null,
            ]))
            ->assertSessionHasErrors(['venue_name', 'venue_address']);
    }

    public function test_capacity_must_be_a_positive_integer(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData(['capacity' => '0']))
            ->assertSessionHasErrors('capacity');

        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData(['capacity' => 'abc']))
            ->assertSessionHasErrors('capacity');
    }

    public function test_invalid_latitude_is_rejected(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData(['latitude' => '999']))
            ->assertSessionHasErrors('latitude');
    }

    public function test_cancelled_is_not_a_valid_creation_status(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('events.store'), $this->validEventData(['status' => 'cancelled']))
            ->assertSessionHasErrors('status');
    }

    public function test_duplicate_titles_receive_unique_slugs(): void
    {
        $organizer = $this->organizer();

        // Different schedule so the second event is not treated as a double submit
        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData());
        $this->actingAs($organizer)->post(route('events.store'), $this->validEventData([
            'start_date' => now()->addDays(8)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(8)->addHours(6)->format('Y-m-d\TH:i'),
        ]));

        $slugs = $organizer->events()->orderBy('id')->pluck('slug');

        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique());
        $this->assertContains('indie-music-festival-2026', $slugs);
    }

    public function test_identical_double_submit_is_idempotent(): void
    {
        $organizer = $this->organizer();
        $payload = $this->validEventData();

        $first = $this->actingAs($organizer)->post(route('events.store'), $payload);
        $second = $this->actingAs($organizer)->post(route('events.store'), $payload);

        $first->assertRedirect(route('dashboard'));
        $second->assertRedirect(route('dashboard'));

        $this->assertSame(1, $organizer->events()->count());
        $this->assertSame(1, $organizer->events()->where('title', 'Indie Music Festival 2026')->count());
    }

    public function test_store_route_is_rate_limited(): void
    {
        $organizer = $this->organizer();
        $invalidPayload = ['title' => ''];

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($organizer)
                ->post(route('events.store'), $invalidPayload)
                ->assertRedirect();
        }

        $this->actingAs($organizer)
            ->post(route('events.store'), $invalidPayload)
            ->assertStatus(429);
    }
}
