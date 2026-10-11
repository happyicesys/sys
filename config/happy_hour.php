<?php

return [
    // First freezer APK build that reads the menu's `happy_hour` slots and charges the promo price.
    // A freezer on an older build is never given slots: it would advertise the full price while
    // mark1 believed in a discount.
    'min_freezer_apk_version' => (int) env('HAPPY_HOUR_MIN_FREEZER_APK', 30),

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
];
