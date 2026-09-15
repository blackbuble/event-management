<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminAnalyticsService
{
    public const PERIODS = ['week', 'month', 'year'];

    /**
     * Full analytics payload for the admin dashboard, scoped to a period.
     *
     * @return array<string, mixed>
     */
    public function forPeriod(string $period, ?int $year = null): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'month';
        $year = $year ?: (int) now()->year;
        $buckets = $this->buckets($period, $year);
        [$start, $end] = $this->window($period, $year);

        return [
            'period' => $period,
            'year' => $year,
            'available_years' => $this->availableYears(),
            'buckets' => array_keys($buckets),
            'labels' => array_values($buckets),
            'events' => $this->eventsSeries($start, $end, $period, $buckets),
            'organizer_revenue' => $this->revenueSeries($start, $end, $period, $buckets, 'organizer'),
            'platform_revenue' => $this->revenueSeries($start, $end, $period, $buckets, 'platform'),
            'totals' => $this->totals($start, $end),
            'most_popular' => $this->mostPopular(),
            'worst_performing' => $this->worstPerforming(),
            'cancelled_events' => $this->cancelledEvents(),
            'categories' => $this->categories(),
            'countries' => $this->groupedVisits('country'),
            'cities' => $this->groupedVisits('city'),
            'devices' => $this->devices(),
            'visits_total' => EventVisit::query()->count(),
        ];
    }

    /**
     * Ordered bucket map: key => human label for the selected year.
     * Numeric year keys are cast to int by PHP, so the key type is mixed.
     *
     * @return array<int|string, string>
     */
    private function buckets(string $period, int $year): array
    {
        if ($period === 'week') {
            $weeks = [];
            $cursor = $this->weekEnd($year)->copy()->startOfWeek();

            for ($i = 0; $i < 12; $i++) {
                $weeks[] = $cursor->copy();
                $cursor->subWeek();
            }

            $buckets = [];
            foreach (array_reverse($weeks) as $week) {
                $buckets[$week->format('o-\WW')] = 'W'.$week->isoWeek();
            }

            return $buckets;
        }

        if ($period === 'year') {
            $buckets = [];
            for ($y = $year - 4; $y <= $year; $y++) {
                $buckets[(string) $y] = (string) $y;
            }

            return $buckets;
        }

        $buckets = [];
        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create($year, $month, 1);
            $buckets[$date->format('Y-m')] = $date->format('M Y');
        }

        return $buckets;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(string $period, int $year): array
    {
        return match ($period) {
            'week' => [$this->weekEnd($year)->copy()->startOfWeek()->subWeeks(11), $this->weekEnd($year)],
            'year' => [Carbon::create($year - 4, 1, 1)->startOfYear(), Carbon::create($year, 12, 31)->endOfYear()],
            default => [Carbon::create($year, 1, 1)->startOfYear(), Carbon::create($year, 12, 31)->endOfYear()],
        };
    }

    private function weekEnd(int $year): Carbon
    {
        return $year === (int) now()->year
            ? now()
            : Carbon::create($year, 12, 28)->endOfYear();
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $earliest = Event::query()->min('created_at');
        $earliestYear = $earliest ? Carbon::parse($earliest)->year : (int) now()->year;
        $from = min($earliestYear, (int) now()->year - 4);

        return range((int) now()->year, $from);
    }

    private function bucketKey(\Carbon\Carbon $date, string $period): string
    {
        return match ($period) {
            'week' => $date->format('o-\WW'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    /**
     * @param  array<string, string>  $buckets
     * @return array<int, int>
     */
    private function eventsSeries(Carbon $start, Carbon $end, string $period, array $buckets): array
    {
        $series = array_fill_keys(array_keys($buckets), 0);

        Event::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'created_at'])
            ->each(function (Event $event) use (&$series, $period) {
                if ($event->created_at === null) {
                    return;
                }

                $key = $this->bucketKey($event->created_at, $period);
                if (isset($series[$key])) {
                    $series[$key]++;
                }
            });

        return array_values($series);
    }

    /**
     * Paid revenue per bucket — organizer (net of fee) or platform (fee only).
     *
     * @param  array<string, string>  $buckets
     * @return array<int, float>
     */
    private function revenueSeries(Carbon $start, Carbon $end, string $period, array $buckets, string $channel): array
    {
        $series = array_fill_keys(array_keys($buckets), 0.0);

        Booking::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at', 'total_amount', 'platform_fee'])
            ->each(function (Booking $booking) use (&$series, $period, $channel) {
                if ($booking->created_at === null) {
                    return;
                }

                $key = $this->bucketKey($booking->created_at, $period);
                if (! isset($series[$key])) {
                    return;
                }

                $series[$key] += $channel === 'platform'
                    ? (float) $booking->platform_fee
                    : (float) $booking->total_amount - (float) $booking->platform_fee;
            });

        return array_map(fn ($value) => round($value, 2), array_values($series));
    }

    /**
     * @return array<string, float|int>
     */
    private function totals(Carbon $start, Carbon $end): array
    {
        $bookings = Booking::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->get(['total_amount', 'platform_fee']);

        $gross = (float) $bookings->sum('total_amount');
        $platform = (float) $bookings->sum('platform_fee');

        return [
            'gross_revenue' => round($gross, 2),
            'platform_revenue' => round($platform, 2),
            'organizer_revenue' => round($gross - $platform, 2),
            'events' => Event::query()->whereBetween('created_at', [$start, $end])->count(),
            'bookings' => Booking::query()->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mostPopular(): array
    {
        $rows = Booking::query()
            ->where('bookings.status', 'confirmed')
            ->selectRaw('event_id, count(*) as bookings, coalesce(sum(total_amount), 0) as revenue')
            ->groupBy('event_id')
            ->orderByDesc('bookings')
            ->limit(5)
            ->get();

        return $this->attachEventTitles($rows);
    }

    /**
     * Published events with the fewest confirmed bookings.
     *
     * @return array<int, array<string, mixed>>
     */
    private function worstPerforming(): array
    {
        return Event::query()
            ->where('status', 'published')
            ->withCount(['bookings' => fn ($query) => $query->confirmed()])
            ->orderBy('bookings_count')
            ->orderBy('title')
            ->limit(5)
            ->get(['id', 'title', 'city', 'capacity'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'city' => $event->city,
                'bookings' => (int) $event->bookings_count,
                'capacity' => $event->capacity,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cancelledEvents(): array
    {
        return Event::query()
            ->where('status', 'cancelled')
            ->latest('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'city', 'start_date'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'city' => $event->city,
                'start_date' => $event->start_date?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Booking>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachEventTitles(Collection $rows): array
    {
        $events = Event::query()
            ->whereIn('id', $rows->pluck('event_id'))
            ->get(['id', 'title', 'city'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($events) {
            $event = $events->get($row->event_id);

            return [
                'id' => $row->event_id,
                'title' => $event instanceof Event ? $event->title : '—',
                'city' => $event instanceof Event ? $event->city : null,
                'bookings' => (int) $row->bookings,
                'revenue' => round((float) $row->revenue, 2),
            ];
        })->all();
    }

    /**
     * Per-category breakdown: events created, paid bookings, gross revenue and
     * the platform's cut, with each category's share of total revenue.
     *
     * @return array<int, array{slug: string, name: string, events: int, bookings: int, revenue: float, platform: float, share: float}>
     */
    private function categories(): array
    {
        $locale = app()->getLocale();

        $labels = Category::query()
            ->get(['slug', 'name', 'name_en'])
            ->mapWithKeys(fn (Category $category) => [$category->slug => $category->label($locale)]);

        $eventCounts = Event::query()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $revenue = Booking::query()
            ->join('events', 'events.id', '=', 'bookings.event_id')
            ->where('bookings.payment_status', 'paid')
            ->selectRaw('events.category as category,
                count(*) as bookings,
                coalesce(sum(bookings.total_amount), 0) as gross,
                coalesce(sum(bookings.platform_fee), 0) as platform')
            ->groupBy('events.category')
            ->get()
            ->keyBy('category');

        $slugs = $eventCounts->keys()
            ->merge($revenue->keys())
            ->filter()
            ->unique()
            ->values();

        $totalRevenue = max(1.0, (float) $revenue->sum('gross'));

        return $slugs
            ->map(fn (string $slug) => [
                'slug' => $slug,
                'name' => $labels[$slug] ?? $slug,
                'events' => (int) ($eventCounts[$slug] ?? 0),
                'bookings' => (int) ($revenue[$slug]->bookings ?? 0),
                'revenue' => round((float) ($revenue[$slug]->gross ?? 0), 2),
                'platform' => round((float) ($revenue[$slug]->platform ?? 0), 2),
                'share' => round((float) ($revenue[$slug]->gross ?? 0) / $totalRevenue * 100, 1),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * Realtime snapshot: visitors active in the last N minutes, broken down by
     * origin (country/city), device and OS, plus the most recent hits.
     *
     * @return array<string, mixed>
     */
    public function realtime(int $minutes = 5): array
    {
        $since = now()->subMinutes($minutes);
        $base = EventVisit::query()->where('created_at', '>=', $since);

        return [
            'window_minutes' => $minutes,
            'active' => (clone $base)->count(),
            'unique' => (int) (clone $base)->distinct()->count('ip'),
            'countries' => $this->topBy(clone $base, 'country'),
            'cities' => $this->topBy(clone $base, 'city'),
            'devices' => $this->topBy(clone $base, 'device'),
            'os' => $this->topBy(clone $base, 'os'),
            'recent' => (clone $base)
                ->with('event:id,title')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (EventVisit $visit) => [
                    'event' => $visit->event?->title,
                    'country' => $visit->country,
                    'city' => $visit->city,
                    'device' => $visit->device,
                    'os' => $visit->os,
                    'ip' => $this->maskIp($visit->ip),
                    'created_at' => $visit->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Daily visit trend (last N days) with device/OS totals for the window.
     *
     * @return array<string, mixed>
     */
    public function daily(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = EventVisit::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'ip', 'device', 'os']);

        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $buckets[now()->subDays($i)->toDateString()] = ['visits' => 0, 'ips' => []];
        }

        foreach ($rows as $row) {
            $key = $row->created_at->toDateString();
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['visits']++;
            $buckets[$key]['ips'][$row->ip ?? 'unknown'] = true;
        }

        return [
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->format('d M'), array_keys($buckets)),
            'dates' => array_keys($buckets),
            'visits' => array_map(fn (array $day) => $day['visits'], array_values($buckets)),
            'unique' => array_map(fn (array $day) => count($day['ips']), array_values($buckets)),
            'devices' => $this->topBy(EventVisit::query()->where('created_at', '>=', $start), 'device'),
            'os' => $this->topBy(EventVisit::query()->where('created_at', '>=', $start), 'os'),
        ];
    }

    /**
     * @param  Builder<EventVisit>  $query
     * @return array<int, array{name: string, count: int}>
     */
    private function topBy($query, string $column, int $limit = 5): array
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as name, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * Mask an IP for privacy in the realtime feed (never expose the full address).
     */
    private function maskIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (str_contains($ip, ':')) {
            $groups = explode(':', $ip);

            return implode(':', array_slice($groups, 0, 3)).'::';
        }

        $parts = explode('.', $ip);

        return count($parts) === 4 ? "{$parts[0]}.{$parts[1]}.x.x" : $ip;
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function groupedVisits(string $column): array
    {
        return EventVisit::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as name, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * @return array<int, array{name: string, count: int, percent: float}>
     */
    private function devices(): array
    {
        $total = EventVisit::query()->count();

        return EventVisit::query()
            ->selectRaw('device as name, count(*) as total')
            ->groupBy('device')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'count' => (int) $row->total,
                'percent' => $total > 0 ? round((int) $row->total / $total * 100, 1) : 0.0,
            ])
            ->all();
    }
}
