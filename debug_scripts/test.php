<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vend = \App\Models\Vend::find(5);
if (!$vend) {
    echo "Vend not found\n";
    exit;
}
$now = \Carbon\Carbon::now();
$dates = [
    $vend->last_updated_at,
    $vend->last_vend_transaction_at,
    $vend->offline_restart_count_datetime
];
if ($vend->is_mqtt) {
    $dates[] = $vend->mqtt_last_updated_at;
}

$lastContact = null;
foreach ($dates as $date) {
    if ($date && ($lastContact === null || $date->gt($lastContact))) {
        $lastContact = $date;
    }
}

$duration = $lastContact ? $now->diffInMinutes($lastContact) : 999999;
$level = 0;
$label = null;

if ($duration >= 720) {
    $level = 6;
    $label = '> 12hr';
} elseif ($duration >= 480) {
    $level = 5;
    $label = '< 12hr';
} elseif ($duration >= 240) {
    $level = 4;
    $label = '< 8hr';
} elseif ($duration >= 120) {
    $level = 3;
    $label = '< 4hr';
} elseif ($duration >= 60) {
    $level = 2;
    $label = '< 2hr';
} elseif ($duration >= 50) {
    $level = 1;
    $label = '< 1hr';
}

echo "Current Level: " . $vend->offline_notification_level . "\n";
echo "New Level: " . $level . "\n";
echo "Duration: " . $duration . "\n";
echo "Last contact: " . ($lastContact ? $lastContact->toIso8601String() : 'null') . "\n";
echo "Will alert? " . ($level > $vend->offline_notification_level ? "YES" : "NO") . "\n";

$alertService = new \App\Services\AlertEmailService();
$recipientCount = $alertService->sendVendOfflineNotificationMail($vend, $label);
echo "Recipients: " . $recipientCount . "\n";
