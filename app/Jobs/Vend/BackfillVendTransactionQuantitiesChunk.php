<?php

namespace App\Jobs\Vend;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class BackfillVendTransactionQuantitiesChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Lowest-priority queue to avoid competing with user-facing workloads.
     *
     * @var string
     */
    public string $queue = 'low';

    public int $tries = 5;

    public int $timeout = 180;

    public function __construct(
        private readonly int $startId,
        private readonly int $endId
    ) {
        //
    }

    public function handle(): void
    {
        $rangeBindings = [
            $this->startId,
            $this->endId,
            $this->startId,
            $this->endId,
        ];

        DB::statement(
            '
            UPDATE vend_transactions vt
            JOIN (
                SELECT vend_transaction_id,
                       COUNT(*) AS total_items,
                       SUM(CASE WHEN CAST(vend_channel_error_code AS SIGNED) IN (0, 6) THEN 1 ELSE 0 END) AS success_items,
                       SUM(CASE WHEN CAST(vend_channel_error_code AS SIGNED) IN (0, 6, 7, 9) THEN 1 ELSE 0 END) AS dispensed_items
                FROM vend_transaction_items
                WHERE vend_transaction_id BETWEEN ? AND ?
                GROUP BY vend_transaction_id
            ) stats ON stats.vend_transaction_id = vt.id
            SET vt.qty = stats.total_items,
                vt.success_qty = stats.success_items,
                vt.dispensed_qty = stats.dispensed_items,
                vt.updated_at = NOW()
            WHERE vt.id BETWEEN ? AND ?
            ',
            $rangeBindings
        );

        DB::statement(
            '
            UPDATE vend_transactions vt
            LEFT JOIN vend_channel_errors vce ON vce.id = vt.vend_channel_error_id
            SET vt.qty = 1,
                vt.success_qty = CASE WHEN CAST(vce.code AS SIGNED) IN (0, 6) THEN 1 ELSE 0 END,
                vt.dispensed_qty = CASE WHEN CAST(vce.code AS SIGNED) IN (0, 6, 7, 9) THEN 1 ELSE 0 END,
                vt.updated_at = NOW()
            WHERE vt.id BETWEEN ? AND ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM vend_transaction_items vti
                  WHERE vti.vend_transaction_id = vt.id
              )
            ',
            [
                $this->startId,
                $this->endId,
            ]
        );
    }
}
