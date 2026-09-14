<?php

namespace App\Http\Controllers\Web;

use App\Enums\EventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreEventRequest;
use App\Http\Requests\Web\UpdateEventRequest;
use App\Http\Requests\Web\UpdateMeetingLinkRequest;
use App\Models\Event;
use App\Services\EventAnalyticsService;
use App\Services\EventLandingService;
use App\Services\EventService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventLandingService $eventLandingService,
        private readonly EventAnalyticsService $eventAnalyticsService,
    ) {}

    /**
     * Organizer analytics for a single event (sales, quota, timeline).
     */
    public function analytics(Event $event)
    {
        Gate::authorize('update', $event);

        return Inertia::render('Events/Analytics', [
            'analytics' => $this->eventAnalyticsService->forEvent($event),
        ]);
    }

    /**
     * Public event landing page. Published events are visible to everyone;
     * drafts/cancelled are visible to the owner (or admin) only.
     */
    public function show(string $slug)
    {
        $event = $this->eventService->findEventBySlug($slug);

        abort_if($event === null, 404);

        $user = request()->user();

        if ($event->status !== 'published' && ! ($user && $user->can('update', $event))) {
            abort(404);
        }

        return Inertia::render('Events/Show', [
            'event' => $this->eventLandingService->getEventPageData($event, $user),
            'is_owner' => (bool) ($user && $user->can('update', $event)),
        ]);
    }

    /**
     * My Events list (paginated) for the signed-in organizer.
     */
    public function index(Request $request)
    {
        $result = $this->eventService->listEventsForOrganizer($request->user()->id);

        return Inertia::render('Events/Index', [
            'events' => $result['events'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Render the create event form.
     */
    public function create()
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('Events/Create', [
            'categories' => EventCategory::options(app()->getLocale()),
        ]);
    }

    /**
     * Store a new event for the authenticated organizer.
     */
    public function store(StoreEventRequest $request)
    {
        try {
            $event = $this->eventService->createEvent($request->validated(), $request->user()->id);
        } catch (LockTimeoutException) {
            $error = app()->getLocale() === 'id'
                ? 'Permintaan sebelumnya masih diproses. Mohon tunggu sebentar dan coba lagi.'
                : 'A previous request is still being processed. Please wait a moment and try again.';

            return redirect()->route('events.create')->with('error', $error);
        }

        $message = app()->getLocale() === 'id'
            ? 'Event "'.$event->title.'" berhasil dibuat.'
            : 'Event "'.$event->title.'" created successfully.';

        return redirect()->route('dashboard')->with('message', $message);
    }

    /**
     * Set or update the meeting link of an online/hybrid event.
     */
    public function updateMeetingLink(UpdateMeetingLinkRequest $request, Event $event)
    {
        $this->eventService->updateMeetingLink($event, $request->validated('meeting_link'));

        $message = app()->getLocale() === 'id'
            ? 'Tautan meeting untuk "'.$event->title.'" tersimpan.'
            : 'Meeting link for "'.$event->title.'" saved.';

        return redirect()->route('events.index')->with('message', $message);
    }

    /**
     * Render the edit form for an existing event.
     */
    public function edit(Event $event)
    {
        Gate::authorize('update', $event);

        return Inertia::render('Events/Edit', [
            'categories' => EventCategory::options(app()->getLocale()),
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'type' => $event->type,
                'category' => $event->category,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'meeting_link' => $event->meeting_link,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'start_date' => $event->start_date?->format('Y-m-d\TH:i'),
                'end_date' => $event->end_date?->format('Y-m-d\TH:i'),
                'capacity' => $event->capacity,
                'status' => $event->status,
                'whatsapp_enabled' => (bool) $event->whatsapp_enabled,
                'image_url' => $event->image ? \Storage::disk('public')->url($event->image) : null,
                'tickets' => $this->eventService->ticketsForEvent($event),
            ],
        ]);
    }

    /**
     * Update an existing event.
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        try {
            $this->eventService->updateEvent($event, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('events.edit', $event)
                ->with('error', $e->getMessage());
        }

        $message = app()->getLocale() === 'id'
            ? 'Event "'.$event->title.'" berhasil diperbarui.'
            : 'Event "'.$event->title.'" updated successfully.';

        return redirect()->route('events.index')->with('message', $message);
    }

    /**
     * Publish a draft event.
     */
    public function publish(Request $request, Event $event)
    {
        Gate::authorize('update', $event);

        try {
            $this->eventService->publishEvent($event);
        } catch (\InvalidArgumentException) {
            return redirect()->route('events.index')->with('error', $this->statusErrorText('publish'));
        }

        $message = app()->getLocale() === 'id'
            ? 'Event "'.$event->title.'" telah dipublikasikan.'
            : 'Event "'.$event->title.'" is now published.';

        return redirect()->route('events.index')->with('message', $message);
    }

    /**
     * Cancel an event.
     */
    public function cancel(Request $request, Event $event)
    {
        Gate::authorize('update', $event);

        try {
            $this->eventService->cancelEvent($event);
        } catch (\InvalidArgumentException) {
            return redirect()->route('events.index')->with('error', $this->statusErrorText('cancel'));
        }

        $message = app()->getLocale() === 'id'
            ? 'Event "'.$event->title.'" telah dibatalkan.'
            : 'Event "'.$event->title.'" has been cancelled.';

        return redirect()->route('events.index')->with('message', $message);
    }

    /**
     * Soft-delete an event (blocked while active bookings exist).
     */
    public function destroy(Request $request, Event $event)
    {
        Gate::authorize('delete', $event);

        try {
            $this->eventService->deleteEvent($event);
        } catch (\InvalidArgumentException) {
            $error = app()->getLocale() === 'id'
                ? 'Event masih memiliki booking aktif dan tidak dapat dihapus.'
                : 'Event still has active bookings and cannot be deleted.';

            return redirect()->route('events.index')->with('error', $error);
        }

        $message = app()->getLocale() === 'id'
            ? 'Event "'.$event->title.'" telah dihapus.'
            : 'Event "'.$event->title.'" has been deleted.';

        return redirect()->route('events.index')->with('message', $message);
    }

    private function statusErrorText(string $action): string
    {
        $isId = app()->getLocale() === 'id';

        if ($action === 'publish') {
            return $isId
                ? 'Hanya event berstatus draft yang dapat dipublikasikan.'
                : 'Only draft events can be published.';
        }

        return $isId
            ? 'Event ini sudah dibatalkan.'
            : 'This event is already cancelled.';
    }

    /**
     * Broadcast the meeting link to confirmed attendees (email / WhatsApp).
     */
    public function sendMeetingLink(Request $request, Event $event)
    {
        Gate::authorize('update', $event);

        $error = $this->sendGuardError($event);

        if ($error !== null) {
            return redirect()->route('events.index')->with('error', $error);
        }

        try {
            $counts = $this->eventService->sendMeetingLinkToAttendees($event);
        } catch (LockTimeoutException) {
            $timeoutError = app()->getLocale() === 'id'
                ? 'Pengiriman sebelumnya masih berjalan. Mohon tunggu sebentar dan coba lagi.'
                : 'A previous send is still in progress. Please wait a moment and try again.';

            return redirect()->route('events.index')->with('error', $timeoutError);
        }

        $message = app()->getLocale() === 'id'
            ? 'Tautan dikirim ke '.$counts['emailed'].' email dan '.$counts['whatsapped'].' nomor WhatsApp.'
            : 'Link sent to '.$counts['emailed'].' email addresses and '.$counts['whatsapped'].' WhatsApp numbers.';

        return redirect()->route('events.index')->with('message', $message);
    }

    /**
     * Locale-aware guards: link must be set, event must not be cancelled,
     * and the event must not already be over.
     */
    private function sendGuardError(Event $event): ?string
    {
        $isId = app()->getLocale() === 'id';

        return match (true) {
            ! $event->meeting_link => $isId
                ? 'Tautan meeting belum diisi. Simpan tautan terlebih dahulu.'
                : 'Meeting link is not set yet. Save the link first.',
            $event->status === 'cancelled' => $isId
                ? 'Event ini sudah dibatalkan — tautan tidak dapat dikirim.'
                : 'This event has been cancelled — the link cannot be sent.',
            $event->end_date?->isPast() => $isId
                ? 'Event ini sudah berakhir — tautan tidak dapat dikirim.'
                : 'This event has already ended — the link cannot be sent.',
            default => null,
        };
    }
}
