<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CityBox-Openapi — THE CityBox integration (doc 2026-08-14)
    |--------------------------------------------------------------------------
    | Token auth + MD5 signing; read endpoints (fleet, stock, par, catalog),
    | ops writes (restock door-open, stock submit), and three dormant webhook
    | receivers. The earlier apiThredDetail surface was retired 2026-08-19
    | (superseded per Citybox). Master switch: CITYBOX_OPENAPI_ENABLED.
    | Credentials come from Citybox; keep them in .env only.
    */
    'openapi' => [
        'enabled' => env('CITYBOX_OPENAPI_ENABLED', false),
        // api.cityboxai.com is the host our credentials are registered on
        // (2026-08-17); the doc's api.icitybox.cn is their CN host and rejects
        // our app_id (app_id有误). Same API on both, cityboxai ~4x lower latency from SG.
        'base_url' => env('CITYBOX_OPENAPI_BASE_URL', 'https://api.cityboxai.com'),
        'app_id' => env('CITYBOX_OPENAPI_APP_ID'),
        'secret' => env('CITYBOX_OPENAPI_SECRET'),
        'open_source' => env('CITYBOX_OPENAPI_OPEN_SOURCE'),
        'timeout' => env('CITYBOX_OPENAPI_TIMEOUT', 10),
        // Attempts on connection/timeout errors only (their API blips briefly a few times a day).
        'retries' => env('CITYBOX_OPENAPI_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provisioning (design §8c / §8f)
    |--------------------------------------------------------------------------
    | Every Smart Chiller vend + its customer is forced under ONE dedicated
    | operator so chiller performance and the CIMB settlement batch never mix
    | with other operators. Seeded by CityboxOperatorSeeder; the code is the
    | stable handle (operators.code), never a hard-coded id.
    */
    'operator_code' => env('CITYBOX_OPERATOR_CODE', 'CB'),
    'vend_prefix_name' => env('CITYBOX_VEND_PREFIX', 'CB'),
    // Their `type` -> mark1 vend_model name. Both SG models are 5 layers
    // (their portal, 2026-08-19). Unknown types get the generic entry + a log.
    'device_models' => [
        'visual-2' => 'CityBox F5 (visual-2)',
        'visual-8' => 'CityBox C5 (visual-8)',
        'unknown' => 'CityBox (unknown type)',
    ],
];
