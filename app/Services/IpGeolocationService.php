<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpGeolocationService
{
    /**
     * Resolve country/city for a public IP.
     *
     * Disabled by default (config('analytics.geoip.enabled')) so no external
     * network call happens until a provider endpoint is configured. Private and
     * reserved IPs are never sent to the provider.
     *
     * @return array{country: ?string, city: ?string}
     */
    public function locate(?string $ip): array
    {
        $unknown = ['country' => null, 'city' => null];

        if (! $ip || ! config('analytics.geoip.enabled')) {
            return $unknown;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $unknown;
        }

        $endpoint = (string) config('analytics.geoip.endpoint');

        if ($endpoint === '' || ! str_contains($endpoint, '{ip}')) {
            return $unknown;
        }

        try {
            $response = Http::timeout((int) config('analytics.geoip.timeout'))
                ->acceptJson()
                ->get(str_replace('{ip}', urlencode($ip), $endpoint));

            if (! $response->successful()) {
                return $unknown;
            }

            $data = $response->json() ?? [];

            return [
                'country' => $data['country_name'] ?? $data['country'] ?? null,
                'city' => $data['city'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning("GeoIP lookup failed for {$ip}: {$e->getMessage()}");

            return $unknown;
        }
    }
}
