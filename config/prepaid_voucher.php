<?php

return [
    // First big-board vending APK (mark1-apk, 30x stream) with the next-visit voucher offer and the
    // "My vouchers" redemption (apk/mark1-apk/UNRELEASED_V329.md). Never a freezer number, never a small board.
    'min_vending_apk_version' => (int) env('PREPAID_VOUCHER_MIN_VENDING_APK', 330),

    // A redemption holds its voucher this long; a TRADE that never comes lets it go back to active.
    'reserve_minutes' => (int) env('PREPAID_VOUCHER_RESERVE_MINUTES', 15),

    // A spent, voided or expired voucher keeps its phone number this many days, then the number is erased
    // (PDPA: kept only as long as the voucher needs it, plus time for a complaint).
    'phone_retention_days' => (int) env('PREPAID_VOUCHER_PHONE_RETENTION_DAYS', 90),

    // Machine API lookups per machine per minute (a phone number is the only key, so guessing is throttled).
    'lookups_per_minute' => (int) env('PREPAID_VOUCHER_LOOKUPS_PER_MINUTE', 12),
];
