<?php

use App\Models\Vend;
use App\Models\VendTemp;
use App\Models\VendSmartAlert;
use Carbon\Carbon;

// Assuming 4752 is the ID or Code. Let's try ID first.
$vend = Vend::find(4752);
if (!$vend) {
    $vend = Vend::where('code', '4752')->first();
}

if (!$vend) {
    echo "Vend 4752 not found.\n";
    exit;
}

echo "Vend ID: " . $vend->id . "\n";
echo "Vend Code: " . $vend->code . "\n";
echo "Current State: " . json_encode($vend->temp_monitoring_state, JSON_PRETTY_PRINT) . "\n";

echo "\nRecent Alerts (T2 below -25):\n";
$alerts = VendSmartAlert::where('vend_id', $vend->id)
    ->where('alert_type', VendSmartAlert::TYPE_T2_BELOW_MINUS_25)
    ->get();

foreach ($alerts as $alert) {
    echo " - Alert ID: $alert->id, Active: $alert->is_active, Severity: $alert->severity\n";
    echo "   Meta: " . json_encode($alert->meta_data) . "\n";
    echo "   Email Sent: " . ($alert->is_email_alert_sent ? 'Yes' : 'No') . "\n";
}

echo "\nRecent T2 Temps (Last 2 hours):\n";
$temps = VendTemp::where('vend_id', $vend->id)
    ->where('type', VendTemp::TYPE_EVAPORATOR)
    ->where('created_at', '>=', now()->subHours(6)) // Look back 6 hours to cover the event in screenshot
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

foreach ($temps as $temp) {
    $val = $temp->value / 10;
    echo " - " . $temp->created_at->toIso8601String() . " : " . $val . "°C\n";
}
