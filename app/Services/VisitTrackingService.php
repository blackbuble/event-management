<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventVisit;
use Illuminate\Http\Request;

class VisitTrackingService
{
    public function __construct(
        private readonly IpGeolocationService $geoLocationService,
    ) {}

    public function record(Request $request, ?Event $event = null): EventVisit
    {
        // `$request->ip()` is proxy-aware once trusted proxies are configured
        // (bootstrap/app.php), so X-Forwarded-For / masked client IPs resolve
        // to the real visitor address.
        $ip = $request->ip();
        $userAgent = (string) $request->userAgent();
        $geo = $this->geoLocationService->locate($ip);

        return EventVisit::create([
            'event_id' => $event?->id,
            'ip' => $ip,
            'country' => $geo['country'],
            'city' => $geo['city'],
            'device' => $this->detectDevice($userAgent),
            'os' => $this->detectOs($userAgent),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
        ]);
    }

    /**
     * Operating system from the User-Agent (order matters: iOS before macOS,
     * Android before Linux).
     */
    public function detectOs(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if ($ua === '') {
            return 'unknown';
        }

        return match (true) {
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad'), str_contains($ua, 'ipod') => 'ios',
            str_contains($ua, 'android') => 'android',
            str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'mac os x'), str_contains($ua, 'macintosh') => 'macos',
            str_contains($ua, 'cros') => 'chromeos',
            str_contains($ua, 'linux') => 'linux',
            default => 'other',
        };
    }

    /**
     * Lightweight UA classification — no external dependency.
     */
    public function detectDevice(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if ($ua === '') {
            return 'unknown';
        }

        if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|curl|wget/', $ua)) {
            return 'bot';
        }

        if (preg_match('/ipad|tablet|kindle|playbook|silk|(android(?!.*mobile))/', $ua)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android|blackberry|windows phone|opera mini/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
