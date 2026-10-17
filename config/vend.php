<?php

return [

    'private_key' => env('VM_DEFAULT_PRIVATE_KEY', '123456789110138A'),

    // Setting/Edit "View Screen" (App\Support\ScreenshotEligibility): the first app build of each
    // family that answers the SCREENSHOT frame. Big board 317 (2026-10-14); freezer 12.
    'screenshot' => [
        'min_vending_apk' => (int) env('SCREENSHOT_MIN_VENDING_APK', 317),
        'min_freezer_apk' => (int) env('SCREENSHOT_MIN_FREEZER_APK', 12),
        // The system's own capture at 07:00 and 17:00 (screenshots:capture-scheduled, Brian
        // 2026-10-16), spread over this many minutes so the fleet's uploads do not land together.
        'auto_enabled' => (bool) env('SCREENSHOT_AUTO_CAPTURE', true),
        'auto_spread_minutes' => (int) env('SCREENSHOT_AUTO_SPREAD_MINUTES', 10),
    ],

    // Ops Dashboard "Needs attention" (App\Services\OpsDashboard\CriticalAttention, Brian 2026-10-17).
    // A board restarts now and then (power cut, watchdog) — on a normal day 89 of 240 did at least
    // once, 25 five times or more — so only a machine that keeps restarting is flagged.
    'attention' => [
        'no_sales_hours' => (int) env('OPS_ATTENTION_NO_SALES_HOURS', 48),
        'apk_restarts_per_day' => (int) env('OPS_ATTENTION_APK_RESTARTS_PER_DAY', 5),
        // A fitted payment rail (card terminal / LCD QR / coin mech) with no sale for this long gets a
        // red frame on its Operation Dashboard badge (App\Support\PaymentRailIdle, Brian 2026-10-17).
        'payment_idle_hours' => (int) env('OPS_ATTENTION_PAYMENT_IDLE_HOURS', 48),
    ],

];
