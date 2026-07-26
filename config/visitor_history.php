<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visitor History retention
    |--------------------------------------------------------------------------
    |
    | How many days of Admin > Visitor History to keep. The
    | `visitor-history:prune` command (scheduled daily at 01:45) deletes both
    | page views and their parent login sessions past this window.
    |
    */

    'retention_days' => (int) env('VISITOR_HISTORY_RETENTION_DAYS', 90),

    /*
    | Master switch. Set VISITOR_HISTORY_ENABLED=false to stop all logging
    | without deploying a code change — the middleware becomes a no-op and the
    | page simply stops gaining new rows.
    */

    'enabled' => (bool) env('VISITOR_HISTORY_ENABLED', true),

    /*
    | Optional header to read the visitor's real IP from, for deployments where a
    | proxy sets one it fully controls (e.g. CF-Connecting-IP behind Cloudflare,
    | or X-Real-IP from nginx). Leave unset and the logger falls back to the LAST
    | X-Forwarded-For hop, which is the entry a client cannot forge.
    */

    'ip_header' => env('VISITOR_HISTORY_IP_HEADER'),

];
