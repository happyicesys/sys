<?php
$request = new \Illuminate\Http\Request();
$request->merge([
    'numberPerPage' => 10,
    'sortKey' => 'date',
    'sortBy' => false,
    'date_from' => \Carbon\Carbon::today()->subDays(3)->startOfDay(),
    'date_to' => \Carbon\Carbon::today()->addWeek()->endOfDay(),
]);

$query = \App\Models\OpsJob::query();
$opsJobs = $query->paginate(10);
$opsJobIds = $opsJobs->pluck('id')->toArray();
$channelStats = \Illuminate\Support\Facades\DB::table('ops_job_item_channels as ojic')
    ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
    ->join('ops_jobs as oj', 'oji.ops_job_id', '=', 'oj.id')
    ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
    ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
    ->leftJoin(\Illuminate\Support\Facades\DB::raw('(
        SELECT id, product_id, qty, date
        FROM (
            SELECT id, product_id, qty, date,
                ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
            FROM product_limits
        ) pl_inner
        WHERE rn = 1
    ) AS pl'), function ($join) {
        $join->on('p.id', '=', 'pl.product_id')
            ->on('pl.date', '=', 'oj.date');
    })
    ->whereIn('oji.ops_job_id', $opsJobIds)
    ->selectRaw('
        oji.ops_job_id,
        SUM(GREATEST(CASE WHEN pl.id AND pl.qty < ojic.capacity THEN (pl.qty - ojic.qty) ELSE (ojic.capacity - ojic.qty) END, 0) * vc.amount) as refillable_amount,
        SUM(GREATEST(CASE WHEN pl.id AND pl.qty < ojic.capacity THEN (pl.qty - ojic.qty) ELSE (ojic.capacity - ojic.qty) END, 0)) as refillable_count
    ')
    ->groupBy('oji.ops_job_id')
    ->get()->keyBy('ops_job_id');

foreach ($opsJobs as $job) {
    dump("job " . $job->id);
    $cStat = $channelStats->get($job->id);
    $job->refillable_amount = $cStat?->refillable_amount ?? 0;
    dump("amount in job model: " . $job->refillable_amount);
}
$resource = \App\Http\Resources\OpsJobResource::collection($opsJobs);
echo json_encode(['opsJobs' => $resource]);
