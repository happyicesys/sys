<?php

namespace App\Jobs\Vend;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Store a machine's MQTT link health for one day in `vend_daily_stats`.
 *
 * The APK (big 306+, small v14+) keeps per-day counters on the device and
 * sends the day's running totals on every "P" heartbeat (MqttLinkStats in
 * cvmqttmodule): session drops, failed connects, client recycles and seconds
 * without a subscribed session. Because the payload is a running TOTAL, not an
 * event, each metric is written as the day's maximum — a late or duplicated
 * heartbeat can never lower it, and a device whose counters were reset
 * mid-day (reinstall) only ever under-reports.
 *
 * Why this exists: 306 / v14 recycle the MQTT client in-process on an
 * MQTT-only outage instead of rebooting the board. The reboot used to be
 * counted in vends.offline_restart_count; without these metrics the outage
 * would disappear from mark1 entirely.
 *
 * One atomic statement per metric (INSERT … ON DUPLICATE KEY UPDATE
 * GREATEST), same unique key as IncrementVendDailyStat. A machine that does
 * not report link health gets no rows at all, so "no data" stays
 * distinguishable from "0 minutes offline".
 */
class RecordVendLinkHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Wire key => vend_daily_stats.metric */
    public const METRICS = [
        'MqttDrops' => 'mqtt_drops',
        'MqttConnFails' => 'mqtt_conn_fails',
        'MqttRecycles' => 'mqtt_recycles',
        'MqttOfflineSec' => 'mqtt_offline_s',
    ];

    /**
     * @param  array<string,int>  $values  metric => running total for $date
     */
    public function __construct(
        public int $vendId,
        public string $vendCode,
        public string $date,
        public array $values,
    ) {}

    public function handle(): void
    {
        foreach ($this->values as $metric => $value) {
            if (! in_array($metric, self::METRICS, true)) {
                continue;
            }

            DB::statement(
                'INSERT INTO vend_daily_stats (vend_id, vend_code, date, metric, count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE count = GREATEST(count, VALUES(count)), updated_at = NOW()',
                [$this->vendId, $this->vendCode, $this->date, $metric, max(0, (int) $value)]
            );
        }
    }
}
