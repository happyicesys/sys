<?php
$items = \App\Models\OpsJobItem::where('status', '>=', 2)->where('refillable_amount', 0)->get();
foreach ($items as $opsJobItem) {
    $stats = \Illuminate\Support\Facades\DB::table('ops_job_item_channels as ojic')
        ->join('ops_jobs as oj', 'oj.id', '=', \Illuminate\Support\Facades\DB::raw($opsJobItem->ops_job_id))
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
        ->where('ojic.ops_job_item_id', $opsJobItem->id)
        ->selectRaw('
                SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < ojic.capacity THEN (pl.qty - COALESCE(ojic.qty, 0)) ELSE (ojic.capacity - COALESCE(ojic.qty, 0)) END, 0) ELSE 0 END * vc.amount) as refillable_amount,
                SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < ojic.capacity THEN (pl.qty - COALESCE(ojic.qty, 0)) ELSE (ojic.capacity - COALESCE(ojic.qty, 0)) END, 0) ELSE 0 END) as refillable_count
            ')->first();
    $opsJobItem->refillable_amount = $stats ? $stats->refillable_amount : 0;
    $opsJobItem->refillable_count = $stats ? $stats->refillable_count : 0;
    $opsJobItem->save();
}
echo "Done backfilling updated values.\n";
