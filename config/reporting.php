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

];
