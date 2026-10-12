<?php

return [
    // First freezer APK build that reads the menu's `happy_hour` slots and charges the promo price.
    // A freezer on an older build is never given slots: it would advertise the full price while
    // mark1 believed in a discount.
    'min_freezer_apk_version' => (int) env('HAPPY_HOUR_MIN_FREEZER_APK', 30),

    // First BIG-board vending APK (mark1-apk, 30x stream) that reads `happy_hour` on `/thumbnails`,
    // prices touchscreen orders from it and tags its TRADEs with `HH` (apk/mark1-apk/UNRELEASED_V315.md).
    // Never compared against a freezer or a small board: the small board shares the applicationId on
    // its own 11+ / 129-145 stream, and its ZC-83A board family is excluded outright
    // (HappyHourEligibility). Never reuse the freezer number for vending — every big board is >= 30.
    'min_vending_apk_version' => (int) env('HAPPY_HOUR_MIN_VENDING_APK', 315),

    // Each slot's SKU is picked this many minutes before the slot starts, from the machine's stock
    // at that moment (Brian, 2026-10-11), so the freezer has it (and the nudge to fetch it) before
    // the slot begins. The planner runs every minute, so 2 leaves one retry.
    'lead_minutes' => 2,

    // The menu carries open slots ending within this many hours (in practice the live one and the
    // next), so a freezer switches slots on its own clock.
    'menu_horizon_hours' => 24,

    // A sale is tagged with the slot that priced it when its unit price equals the slot's promo
    // price and its TRADE time falls inside the slot, give or take this many seconds — a cart
    // started in the slot's last minute is paid just after it ends.
    'sale_grace_seconds' => 300,

    // Vending products that still need a welcome sketch are queued at most this many per hourly
    // sweep (freezer products are never held back): each drawing is a paid image call that can hold
    // a `low` worker for up to 150 s, so ~80 missing vending sketches spread over a working day.
    'vending_sketch_sweep_limit' => (int) env('HAPPY_HOUR_VENDING_SKETCH_SWEEP_LIMIT', 6),
];
