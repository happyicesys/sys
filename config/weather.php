<?php

use App\Services\Weather\Providers\SingaporeRainfallProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Ingestion enabled
    |--------------------------------------------------------------------------
    | Master switch for the every-5-min weather:sync-rainfall cron. Default OFF
    | so a deployment only ingests when it explicitly opts in — set
    | WEATHER_SYNC_ENABLED=true in .env on instances whose region has a provider
    | (e.g. the SG app). Instances without a matching provider (e.g. Indonesia,
    | until one is added) simply leave it unset and the cron never fires.
    | Manual `php artisan weather:sync-rainfall` runs are unaffected by this.
    */
    'enabled' => env('WEATHER_SYNC_ENABLED', false),

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
