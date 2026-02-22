<?php
$job = \App\Models\OpsJob::with([
    'opsJobItems' => function ($q) {
        $q->select([
            'id',
            'cash_amount',
            'cashless_amount',
            'customer_id',
            'notes',
            'ops_job_id',
            'vend_id',
            'cms_transaction_at',
            'cms_transaction_by',
            'cms_transaction_id',
            'is_cash_collected',
            'is_inventory_adjusted',
            'sequence',
            'status',
            'picked_at',
            'picked_by',
            'previous_ops_job_item_id',
            'refillable_amount',
            'refillable_count',
            'completed_at',
            'completed_by',
            'remarks',
            'remarks_updated_at',
            'remarks_updated_by',
            'temp_cash_amount_from_vmc',
            'vend_channel_record_id',
            'verified_at',
            'verified_by',
        ]);
        $q->selectRaw('
        COALESCE(ops_job_items.refillable_amount, (SELECT SUM(
            CASE WHEN products.is_available = 1 THEN GREATEST(
                CASE
                    WHEN pl.id AND pl.qty < ops_job_item_channels.capacity THEN (pl.qty - COALESCE(ops_job_item_channels.qty, 0))
                    ELSE (ops_job_item_channels.capacity - COALESCE(ops_job_item_channels.qty, 0))
                END, 0
            ) ELSE 0 END * vend_channels.amount
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
        )) as refillable_amount
    ');
    }
])->find(1468);
$item = $job->opsJobItems->first();
$resource = \App\Http\Resources\OpsJobItemResource::make($item)->resolve();
echo "Item Refillable_Amount from Resource: " . $resource['refillable_amount'] . "\n";
