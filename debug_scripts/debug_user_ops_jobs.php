<?php

use App\Models\OpsJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$maBin = User::where('name', 'Ma Bin')->first();
$heChao = User::where('name', 'He Chao')->first();
$guilin = User::where('name', 'Guilin')->first();

if (!$maBin || !$heChao || !$guilin) {
    echo "Users not found.\n";
    // Try LIKE
    $maBin = User::where('name', 'like', '%Ma Bin%')->first();
    $heChao = User::where('name', 'like', '%He Chao%')->first();
    $guilin = User::where('name', 'like', '%Guilin%')->first();
}

$users = [
    'Ma Bin' => $maBin,
    'He Chao' => $heChao,
    'Guilin' => $guilin,
];

$date = '2026-01-30';

foreach ($users as $name => $user) {
    if (!$user) {
        echo "$name not found\n";
        continue;
    }
    echo "--------------------------------------------------\n";
    echo "User: $name (ID: {$user->id})\n";

    $jobs = OpsJob::where('delivered_by', $user->id)
        ->whereDate('date', $date)
        ->get();

    echo "Jobs count: " . $jobs->count() . "\n";

    $totalPickedAmount = 0;

    foreach ($jobs as $job) {
        // Calculate picked amount for this job
        $pickedAmount = DB::table('ops_job_item_channels as ojic')
            ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
            ->where('oji.ops_job_id', $job->id)
            ->where('oji.status', '>=', OpsJob::STATUS_PICKED) // Matches query logic
            ->sum(DB::raw('ojic.picked_qty * vc.amount'));

        echo "  Job ID: {$job->id}. Picked Cents: $pickedAmount. Picked Dollars: " . number_format($pickedAmount / 100, 2) . "\n";
        $totalPickedAmount += $pickedAmount;
    }

    echo "Total Picked Cents: $totalPickedAmount\n";
    echo "Total Picked Dollars: " . number_format($totalPickedAmount / 100, 2) . "\n";
}
