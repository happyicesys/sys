<?php

namespace App\Services\Vend;

use App\Jobs\Vend\SendMachineNudge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One queue for every fleet-wide "re-read" frame (Brian, 2026-11-26).
 *
 * The 10:18 outage that day: a few product saves in a row told all ~224 boards to re-read their menu at the
 * same second, every board downloaded every photo at once, and the 20 PHP workers could not keep up, so
 * every page timed out for ten minutes. A frame carries no data (the machine reads whatever is current
 * when it fetches), so it can be sent a little later without being wrong. So a fan-out:
 *
 *  - is PACED: each machine's frame gets its own slot on one shared clock, `units_per_second` units per
 *    second across all senders together (two pushes in the same minute queue behind each other, not on top
 *    of each other). A heavy re-read (photos, banner media) weighs more than a JSON-only one.
 *  - is DE-DUPLICATED per machine and kind: while a machine's frame is waiting, another request for the
 *    same frame is dropped, because the waiting one will read the newer data anyway. A waiting full menu
 *    re-read also covers a light one.
 *
 * Single-machine actions (a button on Setting/Edit, an ops-job Stock In) still send at once through
 * VendJobService. Only senders that loop over many machines come here.
 */
class MachineNudgePacer
{
    public const MENU = 'menu';                  // TYPESYNCAPICHANNELSLOTLIST: menu JSON + every photo

    public const MENU_LIGHT = 'menu_light';      // HAPPYHOUR: the same menu JSON, no photos (big board 315+)

    public const SETTINGS = 'settings';          // TYPESYNCSETTINGSPARAM: /parameters + banner media

    public const PAYMENT_MENU = 'payment_menu';  // PAYMENTMENU: the wallet list only (big board 321+)

    public const WEIGHTS = [
        self::MENU => 5,
        self::SETTINGS => 5,
        self::MENU_LIGHT => 1,
        self::PAYMENT_MENU => 1,
    ];

    /** A waiting frame older than this no longer blocks a new one (a lost job must not mute a machine). */
    private const PENDING_TTL_SECONDS = 1800;

    private const CLOCK_KEY = 'machine-nudge-pacer:clock';

    public static function pendingKey(int $vendId, string $kind): string
    {
        return "machine-nudge-pending:{$vendId}:{$kind}";
    }

    /**
     * Queue one frame per machine, paced. Returns how many were queued (duplicates are not).
     *
     * @param  iterable<int>  $vendIds
     */
    public function schedule(iterable $vendIds, string $kind): int
    {
        if (! isset(self::WEIGHTS[$kind])) {
            throw new \InvalidArgumentException("Unknown machine nudge kind [{$kind}]");
        }

        $queued = 0;
        foreach (collect($vendIds)->map(fn ($id) => (int) $id)->filter()->unique() as $vendId) {
            if ($kind === self::MENU_LIGHT && Cache::has(self::pendingKey($vendId, self::MENU))) {
                continue; // the full re-read already waiting reads the same JSON
            }
            if (! Cache::add(self::pendingKey($vendId, $kind), true, self::PENDING_TTL_SECONDS)) {
                continue;
            }

            SendMachineNudge::dispatch($vendId, $kind)
                ->delay(now()->addSeconds($this->reserveDelay(self::WEIGHTS[$kind])))
                ->onQueue('default');
            $queued++;
        }

        return $queued;
    }

    /**
     * Seconds from now until this frame's slot. The clock is a second plus the units already given out in it;
     * a full second moves the clock on. Shared by every sender, under a lock.
     */
    private function reserveDelay(int $weight): int
    {
        $perSecond = (int) config('vend.nudge_pace.units_per_second', 10);
        $minDelay = max(0, (int) config('vend.nudge_pace.min_delay_seconds', 1));
        if ($perSecond <= 0) {
            return $minDelay;
        }

        $reserve = function () use ($weight, $perSecond, $minDelay): int {
            $earliest = now()->timestamp + $minDelay;
            [$second, $used] = Cache::get(self::CLOCK_KEY, [0, 0]);
            if ($second < $earliest) {
                [$second, $used] = [$earliest, 0];
            }
            if ($used > 0 && $used + $weight > $perSecond) {
                [$second, $used] = [$second + 1, 0];
            }
            $used += $weight;
            Cache::put(self::CLOCK_KEY, [$second, $used], now()->addHour());

            return max(0, $second - now()->timestamp);
        };

        try {
            return Cache::lock('machine-nudge-pacer:lock', 10)->block(5, $reserve);
        } catch (\Throwable $e) {
            // A slow lock must never cost a machine its frame: send it after the minimum delay.
            Log::warning('MachineNudgePacer: no slot reserved, sending after the minimum delay', ['error' => $e->getMessage()]);

            return $minDelay;
        }
    }
}
