<?php

namespace App\Jobs;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Support\OfflineRebootCounter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncP implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** vend_daily_stats metric: offline auto-reboots that day (OfflineRebootCounter). */
    public const METRIC_OFFLINE_REBOOT = 'offline_reboot';

    protected $input;

    protected $vend;

    /**
     * Create a new job instance.
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 2026-08-14: only write fields the heartbeat actually carried. These
        // used to default to 0 / null when the key was absent, so a payload that
        // simply did not report the counter (every APK with debug mode off)
        // overwrote a real stored value with 0. Absent means "no news", not zero.
        $update = [];

        if (array_key_exists('OfflineRestartCount', $this->input)) {
            $update['offline_restart_count'] = $this->input['OfflineRestartCount'];
        }

        if (array_key_exists('OfflineRestartCountDatetime', $this->input)) {
            $update['offline_restart_count_datetime'] = $this->input['OfflineRestartCountDatetime'];
        }

        if ($update === []) {
            return;
        }

        // 2026-10-18: the rise since the stored count is the day's offline auto-reboots — the
        // Ops Dashboard's "APK reboot (connection issue)". Read and written under the row lock,
        // so two heartbeats in flight never count the same reboots twice.
        DB::transaction(function () use ($update) {
            $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)
                ->lockForUpdate()
                ->find($this->vend->getKey());
            if (! $vend) {
                return;
            }

            $reboots = array_key_exists('offline_restart_count', $update)
                ? OfflineRebootCounter::delta(
                    $vend->getRawOriginal('offline_restart_count'),
                    $vend->getRawOriginal('offline_restart_count_datetime'),
                    $update['offline_restart_count'],
                    $update['offline_restart_count_datetime'] ?? $vend->getRawOriginal('offline_restart_count_datetime'),
                )
                : 0;

            $vend->update($update);

            if ($reboots > 0) {
                DB::statement(
                    'INSERT INTO vend_daily_stats (vend_id, vend_code, date, metric, count, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE count = count + VALUES(count), updated_at = NOW()',
                    [$vend->id, (string) $vend->code, Carbon::now()->toDateString(), self::METRIC_OFFLINE_REBOOT, $reboots]
                );
            }
        });
    }
}
