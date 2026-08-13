<?php

namespace App\Jobs\Vend;

use App\Models\ApkSetting;
use App\Services\VendJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fan a TYPESYNCSETTINGSPARAM nudge out to every vend bound to an apk_setting,
 * so an edit in the CMS shows up on the machines without anyone pressing Push.
 *
 * DEBOUNCED, deliberately. Dispatch goes through self::schedule(), which uses a
 * short cache guard so a burst of edits (a multi-file dropzone upload is one
 * HTTP request per file) collapses into ONE push wave. That matters: the
 * biggest setting row binds 84 machines, and every push makes a machine
 * re-fetch its parameters AND re-sync all four media folders — on 4G. Five
 * un-debounced uploads would be 420 publishes and five media re-syncs per
 * machine.
 *
 * Coalescing is safe because the payload carries no data: the machine reads
 * whatever is current when it fetches, so one push after the last edit is
 * strictly better than one per edit.
 */
class PushApkSettingSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Window in which further edits join this push instead of queuing another. */
    private const DEBOUNCE_SECONDS = 5;

    /** Guard TTL — a safety net only; the job clears the key when it runs. */
    private const GUARD_TTL_SECONDS = 60;

    protected int $apkSettingId;

    public function __construct(int $apkSettingId)
    {
        $this->apkSettingId = $apkSettingId;
    }

    public static function cacheKey(int $apkSettingId): string
    {
        return 'apk-setting-push:'.$apkSettingId;
    }

    /**
     * Queue a push for this setting unless one is already pending.
     * Call this from every action that changes what a machine would read.
     */
    public static function schedule(?int $apkSettingId): void
    {
        if (! $apkSettingId) {
            return;
        }

        // Cache::add is atomic: only the first caller in the window wins.
        if (Cache::add(self::cacheKey($apkSettingId), true, self::GUARD_TTL_SECONDS)) {
            self::dispatch($apkSettingId)
                ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS))
                ->onQueue('default');
        }
    }

    public function handle(VendJobService $vendJobService): void
    {
        // Released first, so an edit made while this job runs schedules the
        // next push instead of being swallowed by a stale guard.
        Cache::forget(self::cacheKey($this->apkSettingId));

        // withoutGlobalScopes: OperatorApkSettingScope is auth()-gated and a
        // queue worker has no authenticated user. Explicit so the intent is
        // not mistaken for an oversight.
        $apkSetting = ApkSetting::withoutGlobalScopes()
            ->with('vends:id,code,private_key')
            ->find($this->apkSettingId);

        if (! $apkSetting) {
            return;   // deleted between schedule and run
        }

        foreach ($apkSetting->vends as $vend) {
            try {
                $vendJobService->syncSettingsToVend($vend);
            } catch (\Throwable $e) {
                // One unreachable machine must not stop the rest of the fleet.
                Log::warning('PushApkSettingSync failed for vend', [
                    'apk_setting_id' => $this->apkSettingId,
                    'vend_code' => $vend->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
