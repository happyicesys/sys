<?php

return [

    'private_key' => env('VM_DEFAULT_PRIVATE_KEY', '123456789110138A'),

    // Setting/Edit "View Screen" (App\Support\ScreenshotEligibility): the first app build of each
    // family that answers the SCREENSHOT frame. Big board 317 (2026-10-14); freezer 12.
    'screenshot' => [
        'min_vending_apk' => (int) env('SCREENSHOT_MIN_VENDING_APK', 317),
        'min_freezer_apk' => (int) env('SCREENSHOT_MIN_FREEZER_APK', 12),
    ],

];
