<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IP geolocation (optional)
    |--------------------------------------------------------------------------
    |
    | Visitor origin is resolved from the client IP (proxy-aware). Geolocation
    | is disabled by default so no external call is made until an admin/composer
    | configures a provider endpoint. The endpoint must contain the `{ip}`
    | placeholder, e.g. https://ipapi.co/{ip}/json/
    |
    */
    'geoip' => [
        'enabled' => (bool) env('GEOIP_ENABLED', false),
        'endpoint' => env('GEOIP_ENDPOINT'),
        'timeout' => (int) env('GEOIP_TIMEOUT', 2),
    ],
];
