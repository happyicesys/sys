<?php
$job = \App\Models\OpsJob::with(['opsJobItems' => function($q) {
    $q->select('id', 'ops_job_id', 'status', 'refillable_amount', 'refillable_count');
    $q->selectRaw('COALESCE(ops_job_items.refillable_amount, 99999) as refillable_amount');
}])->find(1468);
$item = $job->opsJobItems->first();
echo "Item: " . json_encode($item) . "\n";
