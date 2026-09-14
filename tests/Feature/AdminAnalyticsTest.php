<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventVisit;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pass')]);
        $user->assignRole('admin');

        return $user;
    }

    private function event(User $organizer, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'user_id' => $organizer->id,
            'title' => 'Analytics Event',
            'description' => 'x',
            'venue_name' => 'V',
            'venue_address' => 'A',
            'type' => 'offline',
            'category' => 'music',
            'city' => 'Jakarta',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(3),
            'status' => 'published',
            'capacity' => 100,
        ], $overrides));
    }

    public function test_non_admin_is_redirected(): void
    {
        $organizer = User::factory()->create(['phone' => '+6281200000000']);
        $organizer->assignRole('organizer');

        $this->actingAs($organizer)->get(route('admin.analytics'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_analytics(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Admin/Analytics')
                    ->where('period', 'month')
                    ->has('labels', 12)
                    ->has('events', 12)
                    ->has('organizer_revenue', 12)
                    ->has('platform_revenue', 12)
            );
    }

    public function test_period_can_be_switched(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.analytics', ['period' => 'year']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period', 'year')->has('labels', 5));
    }

    public function test_analytics_can_select_a_specific_year(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.analytics', ['period' => 'month', 'year' => 2023]))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('year', 2023)
                    ->has('labels', 12)
                    ->has('available_years')
                    ->where('labels.0', 'Jan 2023')
            );
    }

    public function test_revenue_totals_split_platform_and_organizer(): void
    {
        $organizer = User::factory()->create();
        $event = $this->event($organizer);
        $buyer = User::factory()->create();

        Booking::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_amount' => 100000,
            'platform_fee' => 5000,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.analytics'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('totals.gross_revenue', 100000)
                    ->where('totals.platform_revenue', 5000)
                    ->where('totals.organizer_revenue', 95000)
            );
    }

    public function test_event_leaderboards_and_cancelled(): void
    {
        $organizer = User::factory()->create();
        $popular = $this->event($organizer, ['title' => 'Popular Night']);
        $this->event($organizer, ['title' => 'Quiet Talk']);
        $this->event($organizer, ['title' => 'Cancelled Fest', 'status' => 'cancelled']);

        Booking::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $popular->id,
            'status' => 'confirmed',
            'total_amount' => 200000,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.analytics'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('most_popular.0.title', 'Popular Night')
                    ->where('most_popular.0.bookings', 1)
                    ->has('worst_performing', 2)
                    ->where('cancelled_events.0.title', 'Cancelled Fest')
            );
    }

    public function test_category_breakdown(): void
    {
        $this->seed(CategorySeeder::class);

        $organizer = User::factory()->create();
        $music = $this->event($organizer, ['title' => 'Music Fest', 'category' => 'music']);
        $this->event($organizer, ['title' => 'Tech Talk', 'category' => 'technology']);

        Booking::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $music->id,
            'status' => 'confirmed',
            'total_amount' => 100000,
            'platform_fee' => 5000,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.analytics'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('categories.0.slug', 'music')
                    ->where('categories.0.events', 1)
                    ->where('categories.0.bookings', 1)
                    ->where('categories.0.revenue', 100000)
                    ->where('categories.0.platform', 5000)
                    ->where('categories.0.share', 100)
            );
    }

    public function test_realtime_and_daily_visit_analytics(): void
    {
        $organizer = User::factory()->create();
        $event = $this->event($organizer);

        $make = function (string $ip, string $device, string $os, $at) use ($event) {
            $visit = EventVisit::create([
                'event_id' => $event->id, 'ip' => $ip, 'country' => 'Indonesia', 'city' => 'Jakarta',
                'device' => $device, 'os' => $os,
            ]);
            $visit->forceFill(['created_at' => $at])->save();

            return $visit;
        };

        // Realtime window (now)
        $make('8.8.8.8', 'mobile', 'android', now());
        $make('8.8.4.4', 'mobile', 'android', now());
        $make('1.1.1.1', 'desktop', 'windows', now());
        // Outside the realtime window
        $make('9.9.9.9', 'mobile', 'ios', now()->subDays(2));

        $this->actingAs($this->admin())
            ->get(route('admin.analytics'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('realtime.active', 3)
                    ->where('realtime.unique', 3)
                    ->where('realtime.os.0.name', 'android')
                    ->where('realtime.os.0.count', 2)
                    ->where('realtime.devices.0.name', 'mobile')
                    ->has('realtime.recent', 3)
                    ->has('daily.labels', 14)
                    ->has('daily.visits', 14)
                    ->where('daily.visits.13', 3)
                    ->where('daily.visits.11', 1)
            );
    }

    public function test_audience_origin_and_devices(): void
    {
        $organizer = User::factory()->create();
        $event = $this->event($organizer);

        EventVisit::create(['event_id' => $event->id, 'ip' => '8.8.8.8', 'country' => 'Indonesia', 'city' => 'Jakarta', 'device' => 'mobile']);
        EventVisit::create(['event_id' => $event->id, 'ip' => '1.1.1.1', 'country' => 'Singapore', 'city' => 'Singapore', 'device' => 'desktop']);
        EventVisit::create(['event_id' => $event->id, 'ip' => '8.8.4.4', 'country' => 'Indonesia', 'city' => 'Bandung', 'device' => 'mobile']);

        $this->actingAs($this->admin())
            ->get(route('admin.analytics'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('countries.0.name', 'Indonesia')
                    ->where('countries.0.count', 2)
                    ->where('devices.0.name', 'mobile')
                    ->where('devices.0.count', 2)
                    ->where('visits_total', 3)
            );
    }
}
