<?php

namespace App\Console\Commands;

use App\Services\OtaChannelResolver;
use App\Services\OtaCheckNudge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Nudge machines stuck behind the latest published APK to poll for it.
 *
 * Scheduled every 30 minutes between 01:00 and 07:00 (App\Console\Kernel), when
 * machines are idle and an install-restart disturbs nobody. Each run re-selects
 * its targets, so a machine that upgraded at 01:30 is not nudged at 02:00, and a
 * newly published build makes the whole previous version a target automatically.
 *
 * Which machines and why: see OtaCheckNudge::staleTargets() and
 * config('ota.nightly_nudge').
 */
class OtaNudgeStale extends Command
{
    protected $signature = 'ota:nudge-stale
        {--dry-run : List the machines that would be nudged without publishing}';

    protected $description = 'Send OTA_CHECK to online machines running an older APK than the latest published build';

    public function handle(OtaCheckNudge $nudge, OtaChannelResolver $channels): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! config('ota.nightly_nudge.enabled')) {
            $this->info('Nightly OTA nudge is disabled (OTA_NIGHTLY_NUDGE=false).');

            return self::SUCCESS;
        }

        foreach ((array) config('ota.nightly_nudge.channels', []) as $channel => $cfg) {
            if (! $channels->exists($channel)) {
                $this->warn("Skipping unknown OTA channel [{$channel}].");

                continue;
            }

            $latest = $nudge->latestPublishedVersion($channel);

            if ($latest === null) {
                $this->line("[{$channel}] no published build; nothing to nudge.");

                continue;
            }

            $targets = $nudge->staleTargets($channel, (int) ($cfg['min_version_code'] ?? 0));
            $summary = $targets->map(fn ($v) => $v->code.':'.$v->reportedApkVersion())->implode(' ');

            if (! $dryRun) {
                $targets->each(fn ($vend) => $nudge->send($vend));
            }

            $verb = $dryRun ? 'would nudge' : 'nudged';
            $this->line("[{$channel}] latest {$latest}; {$verb} {$targets->count()} machine(s): {$summary}");

            if (! $dryRun && $targets->isNotEmpty()) {
                Log::info('OTA nightly nudge sent.', [
                    'channel' => $channel,
                    'latest_version_code' => $latest,
                    'count' => $targets->count(),
                    'machines' => $summary,
                ]);
            }
        }

        return self::SUCCESS;
    }
}
