<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\VisitTrackingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function publishedEvent(): Event
    {
        $organizer = User::factory()->create();

        return Event::create([
            'user_id' => $organizer->id,
            'title' => 'Landing Event',
            'description' => 'x',
            'venue_name' => 'V',
            'venue_address' => 'A',
            'type' => 'offline',
            'category' => 'music',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(3),
            'status' => 'published',
        ]);
    }

    public function test_guest_visit_is_recorded_with_device(): void
    {
        $event = $this->publishedEvent();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile'])
            ->get(route('events.show', $event->slug))
            ->assertOk();

        $this->assertDatabaseHas('event_visits', [
            'event_id' => $event->id,
            'device' => 'mobile',
            'os' => 'ios',
        ]);
    }

    public function test_owner_view_is_not_tracked(): void
    {
        $event = $this->publishedEvent();

        $this->actingAs($event->user)
            ->get(route('events.show', $event->slug))
            ->assertOk();

        $this->assertDatabaseCount('event_visits', 0);
    }

    public function test_device_detection_classifies_user_agents(): void
    {
        $service = app(VisitTrackingService::class);

        $this->assertSame('mobile', $service->detectDevice('Mozilla/5.0 (Linux; Android 14) AppleWebKit Mobile Safari'));
        $this->assertSame('tablet', $service->detectDevice('Mozilla/5.0 (iPad; CPU OS 17_0) AppleWebKit'));
        $this->assertSame('desktop', $service->detectDevice('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) Safari'));
        $this->assertSame('bot', $service->detectDevice('Googlebot/2.1 (+http://www.google.com/bot.html)'));
        $this->assertSame('unknown', $service->detectDevice(''));
    }

    public function test_os_detection_classifies_user_agents(): void
    {
        $service = app(VisitTrackingService::class);

        $this->assertSame('ios', $service->detectOs('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'));
        $this->assertSame('android', $service->detectOs('Mozilla/5.0 (Linux; Android 14; Pixel 9)'));
        $this->assertSame('windows', $service->detectOs('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
        $this->assertSame('macos', $service->detectOs('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'));
        $this->assertSame('linux', $service->detectOs('Mozilla/5.0 (X11; Linux x86_64)'));
        $this->assertSame('unknown', $service->detectOs(''));
    }
}
