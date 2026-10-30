<?php

namespace App\Jobs\Vend;

use App\Models\HappyHourCampaign;
use App\Models\Vend;
use App\Services\VendJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tell ONE machine to re-read `/parameters` (TYPESYNCSETTINGSPARAM) after something in that payload
 * changed that is not its UI Setting row - PushApkSettingSync covers those. Today: `isGrabEnabled`,
 * derived from the machine's Grab store binding (Brian, 2026-10-30: every setting a machine shows
 * reflects at once, never at the next restart).
 *
 * Debounced per machine like PushApkSettingSync: a Grab move closes one binding and opens another in
 * the same request. Sent only to a vending machine `/parameters` answers for - one bound to a UI
 * Setting, or served the Happy Hour bear without one; every other machine gets a 400 there, so a
 * nudge would only cost it a request.
 */
class PushVendSettingsSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DEBOUNCE_SECONDS = 5;

    /** Guard TTL - a safety net only; the job clears the key when it runs. */
    private const GUARD_TTL_SECONDS = 60;

    public function __construct(protected int $vendId) {}

    public static function cacheKey(int $vendId): string
    {
        return 'vend-settings-push:'.$vendId;
    }

    public static function schedule(?int $vendId): void
    {
        if (! $vendId) {
            return;
        }

        if (Cache::add(self::cacheKey($vendId), true, self::GUARD_TTL_SECONDS)) {
            self::dispatch($vendId)
                ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS))
                ->onQueue('default');
        }
    }

    public function handle(VendJobService $vendJobService): void
    {
        Cache::forget(self::cacheKey($this->vendId));

        $vend = Vend::withoutGlobalScopes()->find($this->vendId);
        if (! $vend || $vend->machine_type !== Vend::MACHINE_TYPE_VENDING_MACHINE) {
            return;
        }

        $answers = $vend->apkSettings()->withoutGlobalScopes()->exists()
            || HappyHourCampaign::hasActiveFor($vend->id);
        if (! $answers) {
            return;
        }

        try {
            $vendJobService->syncSettingsToVend($vend);
        } catch (\Throwable $e) {
            Log::warning('PushVendSettingsSync failed', ['vend_code' => $vend->code, 'error' => $e->getMessage()]);
        }
    }
}
