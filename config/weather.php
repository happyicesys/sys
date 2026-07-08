<?php

use App\Services\Weather\Providers\SingaporeRainfallProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    | Which region's weather source this deployment ingests. One instance per
    | country, so this is normally set once via WEATHER_PROVIDER in .env.
    */
    'default' => env('WEATHER_PROVIDER', 'sg'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    | Each entry maps a key to a WeatherProvider implementation plus its config
    | (passed as an array to the provider constructor). Add a region here — no
    | changes to the command, ingestion service, or storage are needed.
    */
    'providers' => [

        'sg' => [
            'class'    => SingaporeRainfallProvider::class,
            'endpoint' => env('WEATHER_SG_RAINFALL_URL', 'https://api-open.data.gov.sg/v2/real-time/api/rainfall'),
            'timezone' => 'Asia/Singapore',
        ],

    ],

];
