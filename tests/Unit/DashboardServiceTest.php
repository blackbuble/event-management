<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\EventRepository;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(EventRepository $events, BookingRepository $bookings): DashboardService
    {
        return new DashboardService($events, $bookings);
    }

    public function test_organizer_gets_sales_stats_and_mapped_activity(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->shouldReceive('hasRole')->once()->with(['admin', 'organizer'])->andReturn(true);

        $events = Mockery::mock(EventRepository::class);
        $bookings = Mockery::mock(BookingRepository::class);

        $events->shouldReceive('organizerCounts')->once()->with(7)->andReturn([
            'total' => 3, 'active' => 2, 'draft' => 1,
        ]);
        $events->shouldReceive('organizerTicketTotals')->once()->with(7)->andReturn([
            'sold' => 40, 'reserved' => 5,
        ]);
        $bookings->shouldReceive('paidRevenueForOrganizer')->once()->with(7)->andReturn(300000.0);
        $bookings->shouldReceive('confirmedCountForOrganizer')->once()->with(7)->andReturn(3);

        $booking = (new Booking([
            'status' => 'confirmed',
            'total_amount' => 300000,
        ]))->setCreatedAt(Carbon::parse('2026-09-13 10:00:00'));
        $booking->setRelation('event', new Event(['title' => 'Festival', 'slug' => 'festival']));
        $booking->setRelation('user', new User(['name' => 'Budi']));

        $bookings->shouldReceive('recentForOrganizer')->once()->with(7)->andReturn(collect([$booking]));

        $data = $this->service($events, $bookings)->getDashboardData($user);

        $this->assertSame('organizer', $data['view']);
        $this->assertSame(2, $data['stats']['active_events']);
        $this->assertSame(1, $data['stats']['draft_events']);
        $this->assertSame(40, $data['stats']['tickets_sold']);
        $this->assertSame(5, $data['stats']['tickets_reserved']);
        $this->assertSame(300000.0, $data['stats']['revenue']);
        $this->assertSame(3, $data['stats']['confirmed_bookings']);

        $this->assertSame('Festival', $data['recent_activity'][0]['event_title']);
        $this->assertSame('Budi', $data['recent_activity'][0]['attendee_name']);
        $this->assertSame('confirmed', $data['recent_activity'][0]['status']);
        $this->assertSame(300000.0, $data['recent_activity'][0]['total_amount']);
        $this->assertStringStartsWith('2026-09-13T10:00:00', $data['recent_activity'][0]['created_at']);
    }

    public function test_attendee_gets_personal_stats_and_mapped_activity(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 9;
        $user->shouldReceive('hasRole')->once()->with(['admin', 'organizer'])->andReturn(false);

        $events = Mockery::mock(EventRepository::class);
        $bookings = Mockery::mock(BookingRepository::class);

        $bookings->shouldReceive('upcomingCountForAttendee')->once()->with(9)->andReturn(1);
        $bookings->shouldReceive('ticketCountForAttendee')->once()->with(9)->andReturn(2);
        $bookings->shouldReceive('totalSpentForAttendee')->once()->with(9)->andReturn(300000.0);

        $booking = (new Booking([
            'status' => 'pending',
            'total_amount' => 300000,
        ]))->setCreatedAt(Carbon::parse('2026-09-13 11:00:00'));
        $booking->setRelation('event', new Event(['title' => 'Festival', 'slug' => 'festival']));

        $bookings->shouldReceive('recentForAttendee')->once()->with(9)->andReturn(collect([$booking]));

        $data = $this->service($events, $bookings)->getDashboardData($user);

        $this->assertSame('attendee', $data['view']);
        $this->assertSame(1, $data['stats']['upcoming_bookings']);
        $this->assertSame(2, $data['stats']['total_tickets']);
        $this->assertSame(300000.0, $data['stats']['total_spent']);
        $this->assertSame('Festival', $data['recent_activity'][0]['event_title']);
        $this->assertArrayNotHasKey('attendee_name', $data['recent_activity'][0]);
    }
}
