<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vend = \App\Models\Vend::find(5);
$vend->update([
    'offline_notification_level' => 6,
    'is_offline_notification_sent' => true,
    'last_updated_at' => now()->subMinutes(1)
]);

\App\Jobs\SyncOnlineStatus::dispatchSync();

$log = \App\Models\VendLog::where('vend_id', 5)->where('event', \App\Models\VendLog::EVENT_POWER_RESTORED)->latest()->first();
echo json_encode($log);
