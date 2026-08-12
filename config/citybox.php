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
];
