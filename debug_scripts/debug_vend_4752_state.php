<?php

use App\Models\Vend;
use App\Models\VendTemp;
use App\Models\VendSmartAlert;

$vend = Vend::where('code', '4752')->first();
echo "Vend: " . $vend->code . " (ID: " . $vend->id . ")\n";
echo "is_active: " . ($vend->is_active ? 'Yes' : 'No') . "\n";
echo "is_testing: " . ($vend->is_testing ? 'Yes' : 'No') . "\n";
echo "Temp Monitoring State:\n";
print_r($vend->temp_monitoring_state);

echo "\nLatest T2 Temp Record:\n";
$t2 = VendTemp::where('vend_id', $vend->id)
    ->where('type', VendTemp::TYPE_EVAPORATOR)
    ->latest()
    ->first();

if ($t2) {
    echo "Time: " . $t2->created_at->toIso8601String() . "\n";
    echo "Value: " . $t2->value . " (" . ($t2->value / 10) . "C)\n";
} else {
    echo "No T2 record found.\n";
}

echo "\nSmart Alerts:\n";
$alerts = VendSmartAlert::where('vend_id', $vend->id)->get();
foreach ($alerts as $alert) {
    echo "Alert: " . $alert->alert_type . " | Active: " . $alert->is_active . " | Severity: " . $alert->severity . "\n";
    print_r($alert->meta_data);
}
