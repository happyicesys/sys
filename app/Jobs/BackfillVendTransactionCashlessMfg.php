<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-shot backfill for vend_transactions.cashless_mfg over a single ID
 * window. Dispatched repeatedly by BackfillVendTransactionCashlessMfgSeeder
 * to walk the table in light, low-priority chunks.
 *
 * Mirrors VendTransactionService::createVendTransaction:
 *   - Only credit-card txns (payment_method_id = 2) get a value
 *   - Source is vends.acb_vmc_pa_json->CSHL_MFG, trimmed
 *   - Empty string is treated as NULL
 *
 * Idempotent: re-running only touches rows still NULL, so it's safe to
 * dispatch the seeder multiple times if a chunk fails.
 */
class BackfillVendTransactionCashlessMfg implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $startId;
    public int $endId;

    /** Don't let a stuck chunk hold a queue worker forever. */
    public int $timeout = 300;
    public int $tries   = 3;

    public function __construct(int $startId, int $endId)
    {
        $this->startId = $startId;
        $this->endId   = $endId;
        // Default to the low-priority queue so this never elbows out
        // real-time vending traffic. The dispatching command may override
        // via ->onQueue(...).
        $this->onQueue('low');
    }

    public function handle(): void
    {
        // Single set-based UPDATE per chunk — no PHP row loop, no model
        // hydration, no per-row save(). Filters mirror the creation logic so
        // we never overwrite an already-populated value and never touch
        // non-credit-card rows.
        //
        // NULLIF(TRIM(...), '') makes empty-string CSHL_MFG resolve to NULL,
        // matching VendTransactionService.
        $affected = DB::update(
            <<<'SQL'
            UPDATE vend_transactions vt
            INNER JOIN vends v ON v.id = vt.vend_id
            SET vt.cashless_mfg = NULLIF(
                TRIM(JSON_UNQUOTE(JSON_EXTRACT(v.acb_vmc_pa_json, '$.CSHL_MFG'))),
                ''
            )
            WHERE vt.id BETWEEN ? AND ?
              AND vt.payment_method_id = 2
              AND vt.cashless_mfg IS NULL
              AND v.acb_vmc_pa_json IS NOT NULL
              AND JSON_EXTRACT(v.acb_vmc_pa_json, '$.CSHL_MFG') IS NOT NULL
              AND TRIM(JSON_UNQUOTE(JSON_EXTRACT(v.acb_vmc_pa_json, '$.CSHL_MFG'))) <> ''
            SQL,
            [$this->startId, $this->endId]
        );

        Log::info('BackfillVendTransactionCashlessMfg chunk done', [
            'start_id' => $this->startId,
            'end_id'   => $this->endId,
            'updated'  => $affected,
        ]);
    }
}
