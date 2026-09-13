<?php

namespace Tests\Unit;

use App\Mail\MeetingLinkNotification;
use App\Models\Event;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function event(): Event
    {
        $event = new Event([
            'title' => 'Webinar Laravel',
            'meeting_link' => 'https://zoom.us/j/42',
        ]);
        $event->id = 77;

        return $event;
    }

    public function test_queues_email_for_user_with_email_address(): void
    {
        Mail::fake();

        $user = new User(['name' => 'Budi', 'email' => 'budi@example.com']);

        $channels = (new NotificationService)->sendMeetingLink($user, $this->event());

        $this->assertSame(['emailed' => true, 'whatsapped' => false], $channels);
        Mail::assertQueued(MeetingLinkNotification::class, 1);
        Mail::assertQueued(
            MeetingLinkNotification::class,
            fn (MeetingLinkNotification $mail) => $mail->hasTo('budi@example.com')
                && $mail->event->is($this->event())
        );
    }

    public function test_logs_whatsapp_payload_for_user_with_phone(): void
    {
        Mail::fake();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'WA_MEETING_LINK_SENDING')
                && str_contains($message, '+628123456789')
                && str_contains($message, 'https://zoom.us/j/42'));

        $user = new User(['name' => 'Sinta', 'phone' => '+628123456789']);

        $channels = (new NotificationService)->sendMeetingLink($user, $this->event());

        $this->assertSame(['emailed' => false, 'whatsapped' => true], $channels);
        Mail::assertNothingQueued();
    }

    public function test_sends_nothing_for_user_without_channels(): void
    {
        Mail::fake();
        Log::shouldReceive('info')->never();

        $user = new User(['name' => 'Ghost']);

        $channels = (new NotificationService)->sendMeetingLink($user, $this->event());

        $this->assertSame(['emailed' => false, 'whatsapped' => false], $channels);
        Mail::assertNothingQueued();
    }
}
