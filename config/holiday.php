<?php

use App\Services\Holiday\Providers\SingaporePublicHolidayProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Sync enabled
    |--------------------------------------------------------------------------
    | Master switch for the monthly holidays:sync-public cron. Default OFF so a
    | deployment only syncs when it explicitly opts in — set
    | HOLIDAY_SYNC_ENABLED=true in .env on instances whose region has a provider
    | (e.g. the SG app). Instances without a matching provider (e.g. Indonesia,
    | until one is added) simply leave it unset and the cron never fires.
    | Manual `php artisan holidays:sync-public` runs are unaffected by this.
    */
    'sync_enabled' => env('HOLIDAY_SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    | Which region's official holiday source this deployment syncs. One instance
    | per country, so this is normally set once via HOLIDAY_PROVIDER in .env.
    */
    'default' => env('HOLIDAY_PROVIDER', 'sg'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    | Each entry maps a key to a HolidayProvider implementation plus its config
    | (passed as an array to the provider constructor). Add a region here — no
    | changes to the command, ingestion service, or storage are needed.
    |
    | SG public holidays come from the Ministry of Manpower's consolidated
    | dataset on data.gov.sg (covers 2020 onwards, refreshed annually ~Q3).
    */
    'providers' => [

        'sg' => [
            'class'       => SingaporePublicHolidayProvider::class,
            'endpoint'    => env('HOLIDAY_SG_PUBLIC_URL', 'https://data.gov.sg/api/action/datastore_search'),
            'resource_id' => env('HOLIDAY_SG_PUBLIC_RESOURCE_ID', 'd_8ef23381f9417e4d4254ee8b4dcdb176'),
            'timezone'    => 'Asia/Singapore',
        ],

    ],

];
