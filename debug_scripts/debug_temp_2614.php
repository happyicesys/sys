<?php

use App\Models\VendTemp;
use Carbon\Carbon;

$vendId = 831;
$hours = 24;

// Get T1 (Chamber) samples
$t1 = VendTemp::where('vend_id', $vendId)
    ->where('type', VendTemp::TYPE_CHAMBER)
    ->where('created_at', '>=', Carbon::now()->subHours($hours))
    ->orderByDesc('created_at')
    ->limit(20)
    ->get()
    ->map(function ($t) {
        return [
            'time' => $t->created_at->toDateTimeString(),
            'val' => $t->value / 10,
            'type' => 'T1 (Chamber)'
        ];
    });

// Get T2 (Evaporator) samples
$t2 = VendTemp::where('vend_id', $vendId)
    ->where('type', VendTemp::TYPE_EVAPORATOR)
    ->where('created_at', '>=', Carbon::now()->subHours($hours))
    ->orderByDesc('created_at')
    ->limit(20)
    ->get()
    ->map(function ($t) {
        return [
            'time' => $t->created_at->toDateTimeString(),
            'val' => $t->value / 10,
            'type' => 'T2 (Evap)'
        ];
    });

echo "Recent T1 Readings:\n";
foreach ($t1 as $r) {
    echo "{$r['time']} : {$r['val']} C\n";
}

echo "\nRecent T2 Readings:\n";
foreach ($t2 as $r) {
    echo "{$r['time']} : {$r['val']} C\n";
}

// Find Min values in last 24h
$minT1 = VendTemp::where('vend_id', $vendId)
    ->where('type', VendTemp::TYPE_CHAMBER)
    ->where('created_at', '>=', Carbon::now()->subHours($hours))
    ->min('value');

$minT2 = VendTemp::where('vend_id', $vendId)
    ->where('type', VendTemp::TYPE_EVAPORATOR)
    ->where('created_at', '>=', Carbon::now()->subHours($hours))
    ->min('value');

echo "\nMin T1 (Last 24h): " . ($minT1 / 10) . " C\n";
echo "Min T2 (Last 24h): " . ($minT2 / 10) . " C\n";
