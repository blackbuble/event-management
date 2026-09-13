<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\User;
use App\Repositories\EventRepository;
use App\Services\EventService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function repositoryMock(): EventRepository
    {
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findRecentDuplicate')->andReturn(null)->byDefault();

        return $repository;
    }

    private function notificationMock(): NotificationService
    {
        return Mockery::mock(NotificationService::class);
    }

    public function test_delegates_creation_to_repository_with_owner_set(): void
    {
        $repository = $this->repositoryMock();
        $expectedEvent = new Event(['title' => 'Test Event']);

        $repository->shouldReceive('findRecentDuplicate')->once()->andReturn(null);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(function (array $attributes) {
                return $attributes['title'] === 'Test Event'
                    && $attributes['user_id'] === 42
                    && ($attributes['image'] ?? null) === null;
            })
            ->andReturn($expectedEvent);

        $service = new EventService($repository, $this->notificationMock());
        $event = $service->createEvent(['title' => 'Test Event'], 42);

        $this->assertSame($expectedEvent, $event);
    }

    public function test_recent_duplicate_short_circuits_creation_without_storing_image(): void
    {
        Storage::fake('public');
        $repository = $this->repositoryMock();
        $existing = new Event(['title' => 'Webinar Laravel']);
        $file = UploadedFile::fake()->image('banner.jpg');

        $repository->shouldReceive('findRecentDuplicate')->once()->andReturn($existing);
        $repository->shouldReceive('create')->never();

        $service = new EventService($repository, $this->notificationMock());
        $result = $service->createEvent([
            'title' => 'Webinar Laravel',
            'type' => 'online',
            'image' => $file,
        ], 7);

        $this->assertSame($existing, $result);
        $this->assertEmpty(Storage::disk('public')->files('events'));
    }

    public function test_online_event_without_venue_gets_venue_defaults(): void
    {
        $repository = $this->repositoryMock();
        $captured = [];

        $repository->shouldReceive('findRecentDuplicate')->once()->andReturn(null);
        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$captured) {
                $captured = $attributes;

                return new Event;
            });

        $service = new EventService($repository, $this->notificationMock());
        $service->createEvent([
            'title' => 'Webinar Laravel',
            'type' => 'online',
            'meeting_link' => 'https://zoom.us/j/123',
        ], 1);

        $this->assertSame('Online Event', $captured['venue_name']);
        $this->assertSame('https://zoom.us/j/123', $captured['venue_address']);
    }

    public function test_offline_event_venue_is_passed_through_untouched(): void
    {
        $repository = $this->repositoryMock();
        $captured = [];

        $repository->shouldReceive('findRecentDuplicate')->once()->andReturn(null);
        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$captured) {
                $captured = $attributes;

                return new Event;
            });

        $service = new EventService($repository, $this->notificationMock());
        $service->createEvent([
            'title' => 'Offline Event',
            'type' => 'offline',
            'venue_name' => 'Balai Kartini',
            'venue_address' => 'Semarang',
        ], 1);

        $this->assertSame('Balai Kartini', $captured['venue_name']);
        $this->assertSame('Semarang', $captured['venue_address']);
    }

    public function test_uploaded_image_is_stored_and_path_is_passed_to_repository(): void
    {
        Storage::fake('public');
        $repository = $this->repositoryMock();
        $file = UploadedFile::fake()->image('banner.jpg');

        $repository->shouldReceive('findRecentDuplicate')->once()->andReturn(null);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(function (array $attributes) {
                return str_starts_with((string) $attributes['image'], 'events/')
                    && str_ends_with((string) $attributes['image'], '.jpg');
            })
            ->andReturn(new Event);

        $service = new EventService($repository, $this->notificationMock());
        $service->createEvent(['title' => 'Event', 'type' => 'offline', 'image' => $file], 1);

        Storage::disk('public')->assertExists(
            collect(Storage::disk('public')->files('events'))->first()
        );
    }

    public function test_update_meeting_link_delegates_to_repository(): void
    {
        $event = new Event(['title' => 'Webinar']);
        $updated = new Event(['title' => 'Webinar', 'meeting_link' => 'https://zoom.us/j/7']);

        $repository = $this->repositoryMock();
        $repository->shouldReceive('updateMeetingLink')
            ->once()
            ->with($event, 'https://zoom.us/j/7')
            ->andReturn($updated);

        $service = new EventService($repository, $this->notificationMock());

        $this->assertSame($updated, $service->updateMeetingLink($event, 'https://zoom.us/j/7'));
    }

    public function test_send_meeting_link_notifies_each_distinct_attendee_and_counts_channels(): void
    {
        $event = new Event(['title' => 'Webinar', 'meeting_link' => 'https://zoom.us/j/7']);
        $event->id = 77;

        $bothChannels = Mockery::mock(User::class)->makePartial();
        $bothChannels->email = 'both@example.com';
        $bothChannels->phone = '+628111111111';

        $phoneOnly = Mockery::mock(User::class)->makePartial();
        $phoneOnly->email = null;
        $phoneOnly->phone = '+628222222222';

        $emailOnly = Mockery::mock(User::class)->makePartial();
        $emailOnly->email = 'mail@example.com';
        $emailOnly->phone = null;

        $repository = $this->repositoryMock();
        $repository->shouldReceive('confirmedAttendeesForEvent')
            ->once()
            ->with($event->id)
            ->andReturn(collect([$bothChannels, $phoneOnly, $emailOnly]));

        $notification = $this->notificationMock();
        $notification->shouldReceive('sendMeetingLink')
            ->once()->with($bothChannels, $event)
            ->andReturn(['emailed' => true, 'whatsapped' => true]);
        $notification->shouldReceive('sendMeetingLink')
            ->once()->with($phoneOnly, $event)
            ->andReturn(['emailed' => false, 'whatsapped' => true]);
        $notification->shouldReceive('sendMeetingLink')
            ->once()->with($emailOnly, $event)
            ->andReturn(['emailed' => true, 'whatsapped' => false]);

        $service = new EventService($repository, $notification);
        $counts = $service->sendMeetingLinkToAttendees($event);

        $this->assertSame(['emailed' => 2, 'whatsapped' => 2], $counts);
    }

    public function test_list_events_for_organizer_maps_lean_arrays(): void
    {
        $event = new Event([
            'title' => 'Festival',
            'slug' => 'festival',
            'type' => 'online',
            'status' => 'published',
            'meeting_link' => 'https://zoom.us/j/7',
            'start_date' => '2026-10-01 18:00:00',
            'end_date' => '2026-10-01 21:00:00',
        ]);
        $event->id = 3;
        $event->forceFill(['bookings_count' => 5]);

        $paginator = new LengthAwarePaginator(collect([$event]), 15, 10, 1);

        $repository = $this->repositoryMock();
        $repository->shouldReceive('listForOrganizer')
            ->once()
            ->with(9, 10)
            ->andReturn($paginator);

        $service = new EventService($repository, $this->notificationMock());
        $result = $service->listEventsForOrganizer(9);

        $this->assertSame(3, $result['events'][0]['id']);
        $this->assertSame('Festival', $result['events'][0]['title']);
        $this->assertSame('online', $result['events'][0]['type']);
        $this->assertSame('published', $result['events'][0]['status']);
        $this->assertSame('https://zoom.us/j/7', $result['events'][0]['meeting_link']);
        $this->assertSame(5, $result['events'][0]['confirmed_bookings']);
        $this->assertStringStartsWith('2026-10-01T18:00:00', $result['events'][0]['start_date']);

        $this->assertSame(1, $result['pagination']['current_page']);
        $this->assertSame(2, $result['pagination']['last_page']);
        $this->assertSame(10, $result['pagination']['per_page']);
        $this->assertSame(15, $result['pagination']['total']);
    }

    public function test_update_event_delegates_to_repository_without_image_key_when_no_upload(): void
    {
        $event = new Event(['title' => 'Old']);
        $updated = new Event(['title' => 'New']);

        $repository = $this->repositoryMock();
        $repository->shouldReceive('update')
            ->once()
            ->withArgs(fn (Event $target, array $attributes) => $target === $event
                && ! array_key_exists('image', $attributes)
                && $attributes['title'] === 'New')
            ->andReturn($updated);

        $service = new EventService($repository, $this->notificationMock());

        $this->assertSame($updated, $service->updateEvent($event, ['title' => 'New']));
    }

    public function test_update_event_replaces_banner_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $event = new Event(['title' => 'Old']);
        $event->image = 'events/old.jpg';
        Storage::disk('public')->put('events/old.jpg', 'old');

        $file = UploadedFile::fake()->image('banner.jpg');
        $repository = $this->repositoryMock();
        $repository->shouldReceive('update')
            ->once()
            ->withArgs(fn (Event $target, array $attributes) => str_starts_with((string) $attributes['image'], 'events/')
                && str_ends_with((string) $attributes['image'], '.jpg'))
            ->andReturn($event);

        $service = new EventService($repository, $this->notificationMock());
        $service->updateEvent($event, ['image' => $file]);

        Storage::disk('public')->assertMissing('events/old.jpg');
    }

    public function test_publish_event_only_allows_drafts(): void
    {
        $repository = $this->repositoryMock();

        $draft = new Event(['status' => 'draft']);
        $published = new Event(['status' => 'published']);
        $repository->shouldReceive('changeStatus')->once()->with($draft, 'published')->andReturn($published);

        $service = new EventService($repository, $this->notificationMock());
        $this->assertSame($published, $service->publishEvent($draft));

        $this->expectException(\InvalidArgumentException::class);
        $service->publishEvent($published);
    }

    public function test_cancel_event_rejects_already_cancelled(): void
    {
        $repository = $this->repositoryMock();

        $live = new Event(['status' => 'published']);
        $cancelled = new Event(['status' => 'cancelled']);
        $repository->shouldReceive('changeStatus')->once()->with($live, 'cancelled')->andReturn($cancelled);

        $service = new EventService($repository, $this->notificationMock());
        $this->assertSame($cancelled, $service->cancelEvent($live));

        $this->expectException(\InvalidArgumentException::class);
        $service->cancelEvent($cancelled);
    }
}
