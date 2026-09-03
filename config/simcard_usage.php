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
            // Measured 2026-09-03: the API answers in ~1 s per sim (5 sims 6 s,
            // 10 sims 12 s, 30 sims 28 s), so 50-sim chunks never finished
            // inside 15 s and every chunk beyond a handful of sims timed out.
            // 10 sims / 45 s leaves ~3x headroom; 104 sims ≈ 2 min per run.
            'max_per_request' => 10,
            'timeout' => 45,
            // VoicePing (CMI) timestamps are bare yyyyMMddHHmmss in UTC+8.
            'timezone' => 'Asia/Singapore',
        ],

    ],

];
