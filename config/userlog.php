<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Set USERLOG_ENABLED=false in .env to disable capture app-wide without a
    | code change (e.g. during a heavy bulk migration).
    */
    'enabled' => env('USERLOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Deny-list (never logged)
    |--------------------------------------------------------------------------
    | Matched by class basename, so namespace/order is irrelevant. Covers:
    |  - high-volume vending-machine ingestion (also actor-less, so already
    |    skipped by the gate — listed here as belt-and-braces + perf),
    |  - purpose-built domain audit logs that already record their own history,
    |  - framework/infra noise.
    */
    'deny' => [
        'UserLog',
        // Vending-machine ingestion (HTTP/MQTT from machines)
        'VendData', 'VendTemp', 'VendTransaction', 'VendTransactionItem',
        'VendLog', 'VendChannelRecord', 'VendChannelErrorLog', 'VendRecord',
        'PaymentGatewayLog', 'LogData',
        // Existing self-auditing domain logs
        'CustomerStatusLog', 'CustomerContractLog', 'CustomerSettlementLog',
        'RefundTicketLog',
        // Framework / infra
        'PersonalAccessToken', 'Session', 'Notification',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored columns (never appear in the diff)
    |--------------------------------------------------------------------------
    | '*' applies to every model; per-model keys are merged on top. Strip
    | secrets and high-churn blobs so each row stays small and meaningful.
    */
    'ignore_columns' => [
        '*' => [
            'updated_at', 'created_at', 'remember_token', 'password',
            'password_confirmation', 'access_token', 'api_token',
        ],
        // Example: skip the giant cached rollup on Customer.
        'Customer' => ['totals_json'],
    ],
];
