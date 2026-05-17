<?php

namespace App\Jobs\Vend;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Atomically decrement a per-machine, per-day counter in `vend_daily_stats`.
 *
 * Counterpart to IncrementVendDailyStat. When the underlying anomaly is
 * resolved (e.g. a previously-missing VendTransaction finally arrives for a
 * PaymentGatewayLog), the dispatcher fires this job to undo the earlier
 * increment so the daily count stays accurate.
 *
 * Rule: if the row exists and count > 1 → count = count - 1. If count == 1
 * → delete the row entirely (keeps the table clean of zero-count rows so the
 * Customer Index display logic can keep treating "no row" === 0 uniformly).
 * If no row exists, no-op (means the +1 was never written, nothing to undo).
 *
 * Why a SELECT … FOR UPDATE wrapped in a transaction (instead of a single
 * `UPDATE … SET count = count - 1`)?
 *   - We need the read-after-update value to decide whether to delete the
 *     row when count hits 0. Doing it as two statements without locking
 *     would race against another decrement on the same key. The row-level
 *     lock pinned by (vend_id, date, metric) — the table's unique index —
 *     is short-lived and only contends across decrements for the same
 *     machine/day, which is rare.
 *   - Concurrent INCREMENT via INSERT … ON DUPLICATE KEY UPDATE is serialized
 *     by the same unique index, so it cannot interleave inconsistently.
 *
 * Date is passed in by the caller (NOT resolved here) so a decrement that
 * lands after midnight still targets the original bucket day.
 */
class DecrementVendDailyStat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $vendId;
    public string $metric;
    public string $date; // YYYY-MM-DD

    public function __construct(int $vendId, string $metric, string $date)
    {
        $this->vendId = $vendId;
        $this->metric = $metric;
        $this->date = $date;
    }

    public function handle(): void
    {
        if ($this->metric === '') {
            Log::warning('DecrementVendDailyStat skipped: empty metric', [
                'vend_id' => $this->vendId,
                'date' => $this->date,
            ]);
            return;
        }

        DB::transaction(function () {
            $row = DB::table('vend_daily_stats')
                ->where('vend_id', $this->vendId)
                ->where('date', $this->date)
                ->where('metric', $this->metric)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                // Either we never logged (+1 was skipped because the
                // transaction landed within the 5-min window) or the row
                // was already removed by a prior decrement. No-op.
                return;
            }

            if ((int) $row->count <= 1) {
                DB::table('vend_daily_stats')->where('id', $row->id)->delete();
            } else {
                DB::table('vend_daily_stats')
                    ->where('id', $row->id)
                    ->update([
                        'count' => DB::raw('count - 1'),
                        'updated_at' => DB::raw('NOW()'),
                    ]);
            }
        });
    }
}
