<?php

use App\Models\Vend;
use Carbon\Carbon;

$vend = Vend::where('code', '1184')->first();

if ($vend && $vend->customer_movement_history_json) {
    echo "History for Vend 1184:\n";
    $history = $vend->customer_movement_history_json;

    // Sort
    usort($history, function ($a, $b) {
        return strcmp($a['created_at'], $b['created_at']);
    });

    foreach ($history as $entry) {
        $binding = isset($entry['is_binding']) ? ($entry['is_binding'] ? 'Bind' : 'Unbind') : 'Unknown';
        echo "{$entry['created_at']} | {$binding} | Customer ID: {$entry['id']} | {$entry['name']}\n";
    }
} else {
    echo "No history found for Vend 1184\n";
}
