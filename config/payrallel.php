<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payrallel Remote Terminal (smart-freezer card rail)
    |--------------------------------------------------------------------------
    | mark1 commands a Payrallel terminal through their cloud and polls the
    | result; the freezer APK only talks to mark1 (FreezerCardController).
    | Public guide: https://www.payrallel.io/docs/remote-terminal/ and
    | apk/smart-freezer/PAYRALLEL_CARD_RAIL_SURVEY_2026-09-30.md.
    |
    | Inert until `base_url` is set: every call throws CardTerminalException
    | and the device sees the rail as not ready. Tokens are per TERMINAL
    | (one Payrallel "Sales Channel" each) and live encrypted on
    | remote_card_terminals, never in .env — bind one with
    |   php artisan payrallel:bind-terminal {vendCode}
    */
    'base_url' => env('PAYRALLEL_API_BASE_URL'),

    // Their docs show the header as `Authorization: {AUTHORIZATION_VALUE}` without
    // saying whether it is a bare token or "Bearer <token>". {token} is replaced
    // by the terminal's token; confirm the shape when the credentials arrive.
    'authorization_format' => env('PAYRALLEL_AUTHORIZATION_FORMAT', 'Bearer {token}'),

    // Seconds per HTTP call. The APK polls every ~1.5 s, so a slow call must
    // fail fast rather than stack requests behind it.
    'timeout' => (int) env('PAYRALLEL_TIMEOUT', 8),
    'connect_timeout' => (int) env('PAYRALLEL_CONNECT_TIMEOUT', 4),

    /*
    | `preauth` (default since 2026-10-01, Brian; Payrallel's recommended flow —
    | they handle auth_incr on their side): the tap HOLDS the amount, the device's
    | capture after the door opens charges it, a door that fails to open voids
    | (releases) the hold. A hold the device never confirms is captured by
    | `card-payments:reconcile` once `device_void_window_minutes` has passed.
    | Payrallel says a hold lasts about a month.
    | `sale` charges at the tap; capture only marks the sale fulfilled.
    */
    'mode' => env('PAYRALLEL_MODE', 'preauth'),

    // A status read from the device re-queries Payrallel at most this often per
    // intent, so a fast-polling or duplicated client cannot hammer their API.
    'min_query_interval_ms' => (int) env('PAYRALLEL_MIN_QUERY_INTERVAL_MS', 1000),

    // An intent still unresolved this long after it was created is abandoned:
    // mark1 cancels the terminal screen and keeps watching for a late approval.
    // Must exceed the kiosk card window (KioskViewModel.CARD_TIMEOUT_SECONDS = 75).
    'intent_ttl_seconds' => (int) env('PAYRALLEL_INTENT_TTL_SECONDS', 120),

    // How long the reconciler keeps checking an abandoned or cancelled intent
    // for a late approval (which it then voids). Their guide: stop within 2-5 min.
    'late_approval_watch_minutes' => (int) env('PAYRALLEL_LATE_APPROVAL_WATCH_MINUTES', 10),

    // The device may void an approved sale only this long after approval — the
    // door-failed window. Past it the goods are presumed released.
    'device_void_window_minutes' => (int) env('PAYRALLEL_DEVICE_VOID_WINDOW_MINUTES', 15),

    /*
    | AI-decided charge (Brian, 2026-10-03). In `preauth` mode a freezer (app 26+)
    | that sends its session ref with the door-closed capture does NOT charge
    | there: the hold waits (`awaiting_ai`) for that session's verdict in
    | smart_freezer_recognitions, and CardPaymentService::settleAwaitingAi charges
    | what the AI judged, never more than the hold (AiCaptureDecision). A capture
    | with no session ref (app 25) still charges at once. Off = always at once.
    */
    'ai_capture' => (bool) env('PAYRALLEL_AI_CAPTURE', true),

    // A hold still awaiting the AI this long after the door closed is charged in
    // full, so it can never expire uncaptured and hand the goods out free. Brian:
    // "just before hold expiry" — Payrallel says about a month, not per scheme, so
    // 3 days until they confirm the shortest lifetime.
    'ai_capture_backstop_hours' => (int) env('PAYRALLEL_AI_CAPTURE_BACKSTOP_HOURS', 72),

    // Accepted clock skew on device-signed requests (X-Device-Timestamp).
    'device_signature_skew_seconds' => (int) env('PAYRALLEL_DEVICE_SIGNATURE_SKEW_SECONDS', 300),
];
