<?php

return [
    // Master switch — keep false until Citybox delivers the real domain +
    // api_secret and `php artisan citybox:ping` passes against production.
    'enabled' => env('CITYBOX_ENABLED', false),

    // Scheme + host only, no trailing slash (e.g. https://api.citybox.example).
    // The client appends /api/apiThredDetail/<method> itself.
    'base_url' => env('CITYBOX_BASE_URL'),

    // Shared signing secret appended to the sorted-param string before MD5.
    // NEVER commit a real value; the sample in the supplier doc is burned.
    'api_secret' => env('CITYBOX_API_SECRET'),

    // Per-request timeout, seconds. The 1-min poller must never stack requests.
    'timeout' => env('CITYBOX_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | CityBox-Openapi (second supplier API, doc 2026-08-14)
    |--------------------------------------------------------------------------
    | Separate surface from apiThredDetail above: token auth, different sign
    | concatenation, order/refund/close webhooks. Which doc governs is still
    | open (citybox/API_REQUEST_2026-08-14.md Q2), so both configs coexist.
    | app_id / secret / open_source are 待分配 — leave empty until assigned.
    */
    'openapi' => [
        'enabled' => env('CITYBOX_OPENAPI_ENABLED', false),
        'base_url' => env('CITYBOX_OPENAPI_BASE_URL', 'https://api.icitybox.cn'),
        'app_id' => env('CITYBOX_OPENAPI_APP_ID'),
        'secret' => env('CITYBOX_OPENAPI_SECRET'),
        'open_source' => env('CITYBOX_OPENAPI_OPEN_SOURCE'),
        'timeout' => env('CITYBOX_OPENAPI_TIMEOUT', 10),
    ],
];
