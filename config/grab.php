<?php

// Grab (GrabMart / GrabFood partner API). Plan and evidence: GRAB_REVAMP_PLAN_2026-10-29.md.
return [

    /*
     * Inbound webhooks (menu pull, order submit, order state, menu sync state).
     * Grab authenticates with a Passport client-credentials token we issued it.
     *   off     - no check (the pre-2026-10-29 behaviour)
     *   log     - check, log what enforce WOULD refuse, let the call through
     *   enforce - refuse with 401 (Grab re-tokens on a 401 and retries)
     * Between 2026-08-07 and 2026-10-29 every one of ~600k Grab calls carried a
     * valid token from client 3 or 4 (delivery_platform_auth_probes).
     */
    'webhook_auth' => env('GRAB_WEBHOOK_AUTH', 'log'),

    // oauth_clients ids Grab's tokens are issued to ("Grab Mart Delivery", "Grab Mart Production").
    'webhook_client_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GRAB_WEBHOOK_CLIENT_IDS', '3,4'))
    ))),

    /*
     * A menu change tells Grab to re-pull at most once per store per this many
     * seconds (the notify job waits this long, and later changes inside the wait
     * ride along). Grab locks identical notifications for 120 s anyway.
     */
    'menu_notify_delay_seconds' => (int) env('GRAB_MENU_NOTIFY_DELAY_SECONDS', 180),

    /*
     * Grab ends any API pause after 24 h. While on, delivery:sync-store-pause runs
     * every 10 minutes and re-pauses each store mark1 holds paused that Grab
     * reports open again. mark1 refuses those stores' orders anyway, so a pause
     * only stops customers ordering what would be refused.
     */
    'pause_reassert' => (bool) env('GRAB_PAUSE_REASSERT', true),

    // Gap between Grab store-API calls (status / pause): Grab allows 5 a second.
    'store_api_spacing_ms' => (int) env('GRAB_STORE_API_SPACING_MS', 300),

    /*
     * delivery_platform_menu_records (Grab's menu-sync callbacks) older than this
     * many days are deleted nightly. null = never (the first run removes ~2.7M rows).
     */
    'menu_records_keep_days' => env('GRAB_MENU_RECORDS_KEEP_DAYS') !== null
        ? (int) env('GRAB_MENU_RECORDS_KEEP_DAYS')
        : null,
];
