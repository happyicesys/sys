<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find an active vend that belongs to an operator the user wants emails for
// user has email setup for operator_id 26 among others
$vend = \App\Models\Vend::where('is_active', true)
    ->where('is_testing', false)
    ->where('operator_id', 26) // we know leehongjie91@gmail.com is set up for 26
    ->first();

if (!$vend) {
    echo "No vend found for operator 26, trying any active vend and temporarily assigning operator 26\n";
    $vend = \App\Models\Vend::where('is_active', true)->where('is_testing', false)->first();
    $vend->operator_id = 26;
}

echo "Using Vend ID: " . $vend->id . " Code: " . $vend->code . "\n";

// Reset state
$vend->update([
    'offline_notification_level' => 0,
    'is_offline_notification_sent' => false,
    'last_updated_at' => \Carbon\Carbon::now()->subMinutes(52), // More than 50 mins ago
    'mqtt_last_updated_at' => \Carbon\Carbon::now()->subMinutes(52),
    'last_vend_transaction_at' => \Carbon\Carbon::now()->subMinutes(52),
    'offline_restart_count_datetime' => \Carbon\Carbon::now()->subMinutes(52)
]);

// Run SyncOnlineStatus job
echo "Dispatching SyncOnlineStatus job...\n";
\App\Jobs\SyncOnlineStatus::dispatchSync();

echo "Done.\n";
