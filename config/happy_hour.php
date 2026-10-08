<?php

return [
    // First freezer APK build that reads the menu's `happy_hour` slots and charges the promo price.
    // A freezer on an older build is never given slots: it would advertise the full price while
    // mark1 believed in a discount.
    'min_freezer_apk_version' => (int) env('HAPPY_HOUR_MIN_FREEZER_APK', 30),

    // A day's lineup is picked this many minutes before its window opens, so the freezer has the
    // schedule (and the nudge to fetch it) before the first slot starts.
    'lead_minutes' => 10,

    // The menu carries slots ending within this many hours, so a freezer switches slots on its own
    // clock without a fetch per slot.
    'menu_horizon_hours' => 24,
];
