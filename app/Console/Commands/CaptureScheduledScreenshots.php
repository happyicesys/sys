<?php

namespace App\Console\Commands;

use App\Jobs\Screenshot\RequestScheduledScreenshot;
use App\Models\Vend;
use App\Support\ScreenshotEligibility;
use Illuminate\Console\Command;

/**
 * The system's own View Screen capture (Brian, 2026-10-16: "auto capture, 2 times a day, 7am and
 * 5pm"), scheduled at those times in App\Console\Kernel. Every active, online machine that answers a
 * SCREENSHOT frame (ScreenshotEligibility) is asked once; each delivered picture lands in the same
 * last-five history as a click, with no requester ("Auto" on the page). Requests are spread over
 * `vend.screenshot.auto_spread_minutes` so ~200 uploads do not arrive together. A machine busy with a
 * customer answers `busy` and simply has no picture this time.
 */
class CaptureScheduledScreenshots extends Command
{
    protected $signature = 'screenshots:capture-scheduled
        {--dry-run : List the machines that would be asked without asking}';

    protected $description = 'Ask every eligible online machine for one View Screen picture (07:00 / 17:00)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! config('vend.screenshot.auto_enabled')) {
            $this->info('Auto screen capture is disabled (SCREENSHOT_AUTO_CAPTURE=false).');

            return self::SUCCESS;
        }

        $targets = collect();
        Vend::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('is_disposed', false)
            ->where('is_online', true)
            ->whereNotNull('code')
            ->whereIn('machine_type', [Vend::MACHINE_TYPE_VENDING_MACHINE, Vend::MACHINE_TYPE_SMART_FREEZER])
            ->select(['id', 'code', 'machine_type', 'apk_version_code', 'apk_ver_json', 'apk_checked_in_at'])
            ->chunkById(500, function ($vends) use ($targets) {
                foreach ($vends as $vend) {
                    if (ScreenshotEligibility::supports($vend)) {
                        $targets->push($vend);
                    }
                }
            });

        if ($dryRun) {
            $this->info($targets->count().' machine(s) would be asked: '.$targets->pluck('code')->implode(', '));

            return self::SUCCESS;
        }

        $window = max(0, (int) config('vend.screenshot.auto_spread_minutes', 10)) * 60;
        $step = $targets->count() > 1 ? intdiv($window, $targets->count()) : 0;

        foreach ($targets->values() as $i => $vend) {
            RequestScheduledScreenshot::dispatch($vend->id)->delay(now()->addSeconds($i * $step));
        }

        $this->info('Asked '.$targets->count().' machine(s) for a screen capture.');

        return self::SUCCESS;
    }
}
