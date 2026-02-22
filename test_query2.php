<?php
$items = \Illuminate\Support\Facades\DB::select("
    SELECT ops_job_items.id, COALESCE(ops_job_items.refillable_amount, (SELECT SUM(
        GREATEST(
            CASE
                WHEN pl.id IS NOT NULL AND pl.qty < ops_job_item_channels.capacity THEN pl.qty - ops_job_item_channels.qty
                ELSE ops_job_item_channels.capacity - ops_job_item_channels.qty
            END, 0
        ) * vend_channels.amount
    )
    FROM ops_job_item_channels
    JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
    LEFT JOIN products ON vend_channels.product_id = products.id
    LEFT JOIN (
        SELECT id, product_id, qty, date
        FROM (
            SELECT id, product_id, qty, date,
                ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
            FROM product_limits
        ) pl_inner
        WHERE rn = 1
    ) AS pl ON products.id = pl.product_id AND pl.date = (SELECT date FROM ops_jobs WHERE ops_jobs.id = ops_job_items.ops_job_id)
    WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
    AND products.is_available = 1
    )) as refillable_amount
    FROM ops_job_items
    WHERE ops_job_id = 1468
");
foreach ($items as $i) {
    echo "Item {$i->id} Refillable: {$i->refillable_amount}\n";
}
