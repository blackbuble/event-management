<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingLinkNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly Event $event,
        private readonly string $mailLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailLocale === 'id'
                ? 'Tautan Event Online: '.$this->event->title
                : 'Online Event Link: '.$this->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.events.meeting-link',
            with: [
                'locale' => $this->mailLocale,
                'eventName' => $this->event->title,
                // Rendered in the app timezone with its real abbreviation — never hardcode "WIB"
                'eventStart' => $this->event->start_date
                    ?->setTimezone(config('app.timezone'))
                    ->format('d M Y H:i T'),
                'meetingLink' => $this->event->meeting_link,
                'recipientName' => $this->recipient->name,
            ],
        );
    }
}
