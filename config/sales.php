<?php

/*
 * Sales-truth rules that are policy, not code. See
 * NA_ERROR_CODE_PLAN_2026-09-08.md and App\Support\DispenseVerdict.
 */
return [

    /*
     * A TRADE frame's own TIME (device clock) is trusted only inside this window
     * behind "now" (days) and ahead of it (seconds). Outside it the sale is
     * booked at arrival time and stamped meta_json.frame_time.rejected so it can
     * be audited. Brian, 2026-09-08: 30 days — machines replay a month of queued
     * TRADEs after a reconnect, while ~3.7% of frames carry a clock that is years
     * off (2070, 2030…) and must not be believed.
     */
    'trade_max_days_back' => (int) env('SALES_TRADE_MAX_DAYS_BACK', 30),
    'trade_max_seconds_ahead' => (int) env('SALES_TRADE_MAX_SECONDS_AHEAD', 300),

    /*
     * Nightly marker: gateway rows whose day is over and whose TRADE never came
     * get channel error 99 ("Machine transaction not found (NA)"). No grace
     * period — the day boundary is the rule. The watermark lives in
     * settings.missing_trade_marked_until; on a fresh install (NULL) the first
     * run starts here.
     */
    'missing_trade_floor' => env('SALES_MISSING_TRADE_FLOOR', '2026-08-01'),
    'missing_trade_chunk' => 500,

    /*
     * Where past days touched by a late TRADE / orphan row are collected until
     * `reconcile:sales-rollups --dirty` rebuilds them (Redis set; the cache
     * driver is file in prod, so this is NOT Cache). 'array' is for tests.
     */
    'dirty_days_store' => env('SALES_DIRTY_DAYS_STORE', 'redis'),
    'dirty_days_connection' => env('SALES_DIRTY_DAYS_REDIS_CONNECTION', 'default'),
    'dirty_days_key' => 'sales:rollups:dirty-days',
];
