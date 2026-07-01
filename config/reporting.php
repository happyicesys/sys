<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting floor date (data genesis)
    |--------------------------------------------------------------------------
    |
    | The earliest date the system treats as having reliable data. Every
    | AVERAGE and ACCUMULATED figure derived from historical time-series data
    | (period summaries, lifetime vend_records, etc.) must ignore anything
    | before this date — "assume our system calculation only takes place on"
    | this date. Each per-country deployment can override it via env.
    |
    | Stored as YYYY-MM-DD (first day of the genesis month).
    |
    */

    'floor_date' => env('REPORTING_FLOOR_DATE', '2023-01-01'),

    /*
    |--------------------------------------------------------------------------
    | Transactions-index daily rollup — MASTER kill switch
    |--------------------------------------------------------------------------
    |
    | ONE flag controls the whole feature:
    |   - OFF (default): the nightly/weekly rollup population does NOT run, and
    |     (once wired) the read path uses the live query. Feature fully dormant —
    |     zero effect on users. This is your instant rollback: set false, done.
    |   - ON: the schedule populates vend_transaction_daily_summaries, and the
    |     read path reads past days from the summary + computes today live.
    |
    | Manual `php artisan transactions:rollup-daily` / `transactions:rollup-verify`
    | ALWAYS run (for backfill + parity checking) regardless of this flag.
    |
    | KEEP FALSE until transactions:rollup-verify shows an empty diff on a
    | representative range. The read path (when wired) is only eligible for the
    | default whole-day view with NO code/channel/error/payment-method/customer
    | filter; any such filter falls back to the live query. See
    | docs/perf/transactions_daily_rollup_spec.md.
    |
    */

    'transactions_rollup_enabled' => env('ENABLE_TRANSACTIONS_ROLLUP', false),

];
