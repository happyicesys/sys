<?php

use App\Services\SimcardUsage\Providers\VoicePingUsageProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    | Each entry maps a key to a SimcardUsageProvider implementation plus its
    | config (passed as an array to the provider constructor). A telco opts in
    | by carrying the key in telcos.usage_provider; telcos without one are
    | skipped by simcards:sync-usage, so no master switch is needed — an
    | instance with no mapped telco simply no-ops.
    */
    'providers' => [

        'voiceping' => [
            'class' => VoicePingUsageProvider::class,
            'endpoint' => env('VOICEPING_USAGE_URL', 'https://usage.voiceping.com/api/sim-info'),
            'max_per_request' => 50,
            'timeout' => 15,
            // VoicePing (CMI) timestamps are bare yyyyMMddHHmmss in UTC+8.
            'timezone' => 'Asia/Singapore',
        ],

    ],

];
