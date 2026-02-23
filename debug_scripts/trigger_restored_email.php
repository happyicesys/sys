<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vend = \App\Models\Vend::find(5);
echo "Using Vend ID: " . $vend->id . " Code: " . $vend->code . "\n";

// Reset state so it's online again but has an offline notification level > 0
$vend->update([
    'last_updated_at' => \Carbon\Carbon::now()->subMinutes(2), // Less than 15 mins ago
    'mqtt_last_updated_at' => \Carbon\Carbon::now()->subMinutes(2),
    'last_vend_transaction_at' => \Carbon\Carbon::now()->subMinutes(2),
    'offline_restart_count_datetime' => \Carbon\Carbon::now()->subMinutes(2)
]);

// Run SyncOnlineStatus job
echo "Dispatching SyncOnlineStatus job for restoration...\n";
\App\Jobs\SyncOnlineStatus::dispatchSync();

echo "Done.\n";
