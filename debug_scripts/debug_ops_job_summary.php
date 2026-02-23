<?php

use App\Models\OpsJob;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find a job with non-zero picked amount or large items
$jobs = OpsJob::orderBy('id', 'desc')->take(50)->get();

foreach ($jobs as $job) {
    $itemCount = DB::table('ops_job_items')->where('ops_job_id', $job->id)->count();
    $pickedSum = DB::table('ops_job_item_channels as ojic')
        ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
        ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
        ->where('oji.ops_job_id', $job->id)
        ->sum('ojic.picked_qty');

    // Only inspect if we have activity
    if ($pickedSum > 0) {
        echo "\n--------------------------------------------------\n";
        echo "Analyzing OpsJob ID: {$job->id}\n";
        echo "Items: $itemCount, Picked Qty Sum: $pickedSum\n";

        // Check for duplication
        $count = DB::table('ops_job_item_channels as ojic')
            ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
            ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
            ->leftJoin('unit_costs as uc', function ($join) {
                $join->on('p.id', '=', 'uc.product_id')
                    ->where('uc.is_current', '=', true);
            })
            ->where('oji.ops_job_id', $job->id)
            ->count();

        $realCount = DB::table('ops_job_item_channels as ojic')
            ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
            ->where('oji.ops_job_id', $job->id)
            ->count();


        echo "Total join rows: $count vs Real rows: $realCount\n";

        $pickedAmount = DB::table('ops_job_item_channels as ojic')
            ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
            ->where('oji.ops_job_id', $job->id)
            ->sum(DB::raw('ojic.picked_qty * vc.amount'));

        echo "Picked Amount: $pickedAmount\n";

        if ($count > $realCount) {
             echo "!!! DUPLICATION DETECTED !!!\n";
        }

        // Check MAX Qty and MAX Amount
        $maxQty = DB::table('ops_job_item_channels as ojic')
            ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->where('oji.ops_job_id', $job->id)
            ->max('ojic.picked_qty');

        $maxAmount = DB::table('vend_channels as vc')
            ->join('ops_job_item_channels as ojic', 'ojic.vend_channel_id', '=', 'vc.id')
            ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
            ->where('oji.ops_job_id', $job->id)
            ->max('vc.amount');

        echo "Max Picked Qty: $maxQty\n";
        echo "Max Channel Amount: $maxAmount\n";

        break;
    }
}
