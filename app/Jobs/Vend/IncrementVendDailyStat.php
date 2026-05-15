<?php

namespace App\Jobs\Vend;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Atomically increment a per-machine, per-day counter in `vend_daily_stats`.
 *
 * Use this for non-sales hardware / diagnostic / protocol events that you
 * want to track at a daily granularity per machine. The first known caller is
 * VendDataService::processVendData() in the PWRON branch.
 *
 * Why a job (instead of inline DB write)?
 *   - The vend POST hot path already dispatches everything else asynchronously
 *     (UpdateApkVersion, SyncVendChannels, etc.). Following the same pattern
 *     keeps the HTTP/MQTT entry latency unchanged.
 *   - Runs on the 'low' queue so a backlog cannot starve the 'default'/'high'
 *     queues that handle billing-critical work.
 *
 * Why no read-modify-write?
 *   - INSERT ... ON DUPLICATE KEY UPDATE is one atomic statement at the DB
 *     level, so concurrent PWRONs from the same machine on the same day
 *     cannot lose increments.
 *
 * Date is resolved at dispatch time (passed in by the caller). This is
 * intentional: if the queue lags across midnight, the increment still lands
 * on the day the event actually happened.
 */
class IncrementVendDailyStat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $vendId;
    public string $vendCode;
    public string $metric;
    public string $date; // YYYY-MM-DD

    /**
     * @param  int|string  $vendCode  Stored as string in DB; cast for safety.
     * @param  string|null $date      YYYY-MM-DD. Defaults to today (app TZ).
     */
    public function __construct(int $vendId, $vendCode, string $metric, ?string $date = null)
    {
        $this->vendId = $vendId;
        $this->vendCode = (string) $vendCode;
        $this->metric = $metric;
        $this->date = $date ?: Carbon::now()->toDateString();
    }

    public function handle(): void
    {
        // Guard: empty metric should never happen, but if it does we'd rather
        // skip than corrupt the table with blank-key rows.
        if ($this->metric === '') {
            Log::warning('IncrementVendDailyStat skipped: empty metric', [
                'vend_id' => $this->vendId,
                'date' => $this->date,
            ]);
            return;
        }

        DB::statement(
            'INSERT INTO vend_daily_stats (vend_id, vend_code, date, metric, count, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE count = count + 1, updated_at = NOW()',
            [$this->vendId, $this->vendCode, $this->date, $this->metric]
        );
    }
}
