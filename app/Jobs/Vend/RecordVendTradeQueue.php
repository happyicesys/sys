<?php

namespace App\Jobs\Vend;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Store a machine's unsent-TRADE backlog in `vend_daily_stats` (metrics
 * `trade_queue` and `trade_queue_age_s`, today's row).
 *
 * Big 307+ / small v15+ keep every TRADE on disk until mark1 answers HTTP 200
 * for it (TradeOutbox) and report the backlog on each "P" heartbeat: `TrdQ` =
 * trades still waiting, `TrdQAge` = seconds the oldest has waited. A value
 * that stays above zero means the machine is selling but those sales have not
 * reached mark1. Background: the 2026-09-26 check of NETS charges with no
 * TRADE (apk/mark1-apk/UNRELEASED_V307.md).
 *
 * Unlike RecordVendLinkHealth (running totals, day's MAX) this is a GAUGE, so
 * the row holds the LATEST reading. `updated_at` is the reading's own time and
 * a write only lands if it is not older than what is stored, so two jobs
 * running out of order on the low queue cannot bring back a stale value.
 * A machine that does not report gets no row: "no data", never "0 unsent".
 */
class RecordVendTradeQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const METRIC_COUNT = 'trade_queue';

    public const METRIC_AGE = 'trade_queue_age_s';

    public function __construct(
        public int $vendId,
        public string $vendCode,
        public string $date,
        public int $count,
        public int $ageSeconds,
        public string $reportedAt,
    ) {}

    public function handle(): void
    {
        foreach ([self::METRIC_COUNT => $this->count, self::METRIC_AGE => $this->ageSeconds] as $metric => $value) {
            DB::statement(
                'INSERT INTO vend_daily_stats (vend_id, vend_code, date, metric, count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    count = IF(VALUES(updated_at) >= updated_at, VALUES(count), count),
                    updated_at = GREATEST(updated_at, VALUES(updated_at))',
                [$this->vendId, $this->vendCode, $this->date, $metric, max(0, $value), $this->reportedAt, $this->reportedAt]
            );
        }
    }
}
