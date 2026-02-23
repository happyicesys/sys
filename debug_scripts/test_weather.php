<?php

use App\Services\WeatherService;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new WeatherService();
$start = Carbon::today()->subDays(30);
$end = Carbon::today();
$lat = 1.3521;
$lng = 103.8198;

echo "Fetching weather for Singapore ($lat, $lng) from {$start->toDateString()} to {$end->toDateString()}...\n";

// Bypassing the cache/db logic slightly to just check what the logic *would* return,
// strictly speaking I should check the DB first to see what is stored.
// Let's just call the public method which will hit DB.

$results = $service->getDailyWeatherForRange($start, $end, $lat, $lng);

foreach ($results as $date => $icon) {
    // I need to retrieve the original code to really know.
    // But the service only returns icons.
    // Use the DB to get the actual code.
    $record = \App\Models\DailyWeatherHistory::where('date', $date)
        ->where('latitude', $lat)
        ->where('longitude', $lng)
        ->first();

    $code = $record ? $record->weather_code : 'N/A';
    echo "$date: Code=$code, Icon=$icon\n";
}
