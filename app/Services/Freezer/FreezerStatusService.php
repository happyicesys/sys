<?php

namespace App\Services\Freezer;

use App\Models\Vend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persists smart-freezer telemetry (`sg.mark1.freezer`) that has no equivalent on a vending machine.
 *
 * Design notes:
 *
 * - **Single statement per packet.** Every write is one UPDATE using `JSON_MERGE_PATCH`, so there is
 *   no read-modify-write and no model hydration/events. A status packet (900 s) and a self-check
 *   (daily) can therefore share `freezer_status_json` without racing each other.
 * - **Out-of-order safe.** The device buffers telemetry in an outbox and drains it on reconnect, so
 *   a stale POWERRESTORED can arrive *after* a newer POWERCUT. Both writes are guarded on the event
 *   timestamp, so a late packet can never clear a newer state.
 * - **Device-supplied clocks are untrusted.** An offline freezer has no NTP, so a `ts` far from the
 *   server clock is ignored in favour of server time.
 */
class FreezerStatusService
{
    /** Reject a device timestamp more than this far from server time (clock drift while offline). */
    private const MAX_CLOCK_SKEW_MINUTES = 60;

    /**
     * A numeric `ts` above this is epoch-MILLISECONDS (`System.currentTimeMillis()`, the APK's
     * convention), below it epoch-seconds. 1e11 s ≈ year 5138; 1e11 ms ≈ 1973 — unambiguous.
     */
    private const EPOCH_MS_THRESHOLD = 100_000_000_000;

    /**
     * FREEZERSTATUS — the 900 s serviceability snapshot (power, compressor, locks, cameras).
     */
    public function syncStatus(Vend $vend, array $input): void
    {
        $status = $this->normaliseStatus($input);

        $this->mergeJson($vend, [
            // (object) cast matters when every key was absent: PHP json_encode([]) is "[]", and a
            // JSON_MERGE_PATCH of `"status": []` REPLACES the stored status object with an empty
            // array. An empty object "{}"` merges as a no-op, which is the intended semantics.
            'status' => $status === [] ? (object) [] : $status,
            'status_at' => $this->now()->toDateTimeString(),
        ]);
    }

    /**
     * SELFCHK — the daily diagnostic. Shares the column; merged under its own key.
     */
    public function syncSelfCheck(Vend $vend, array $input): void
    {
        $this->mergeJson($vend, [
            'selfcheck' => [
                'passed' => (bool) ($input['passed'] ?? false),
                'errors' => array_values(array_filter((array) ($input['errors'] ?? []), 'is_scalar')),
                'msgs' => array_values(array_filter((array) ($input['msgs'] ?? []), 'is_scalar')),
            ],
            'selfcheck_at' => $this->now()->toDateTimeString(),
        ]);
    }

    /**
     * POWERCUT — mains lost. The freezer runs on its reserve battery from here.
     *
     * Sent MQTT QoS-1 the instant `onPowerState` reports 已断开, before the pending sale is settled,
     * because the packet is sub-second and a settle is not.
     */
    public function recordPowerCut(Vend $vend, array $input): void
    {
        $at = $this->eventTime($input);

        // Latest-event-wins across BOTH power columns: a stale CUT drained late must not re-raise
        // the battery flag after a NEWER RESTORED has already cleared it (guarding only against
        // last_power_cut_at would let it through).
        $applied = DB::table('vends')
            ->where('id', $vend->id)
            ->where(function ($q) use ($at) {
                $q->whereNull('last_power_cut_at')->orWhere('last_power_cut_at', '<', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('last_power_restored_at')->orWhere('last_power_restored_at', '<', $at);
            })
            ->update([
                'last_power_cut_at' => $at,
                'is_on_battery' => true,
                'updated_at' => $this->now(),
            ]);

        if ($applied) {
            $this->mergeJson($vend, [
                'power' => [
                    'state' => 'cut',
                    'at' => $at->toDateTimeString(),
                    'txn_in_flight' => $input['txn_in_flight'] ?? null,
                ],
            ]);
        }
    }

    /**
     * POWERRESTORED — mains back. Clears the battery flag and records the outage window.
     */
    public function recordPowerRestored(Vend $vend, array $input): void
    {
        $at = $this->eventTime($input);

        // Same cross-column guard as recordPowerCut(): a stale RESTORED from a PREVIOUS outage,
        // drained after a newer CUT, must not clear the battery flag.
        //
        // '<=' (not '<') against the CUT column: timestamps are second-granularity, so a sub-second
        // blip yields CUT and RESTORED with the SAME `at`. RESTORED wins that tie — a blip ends
        // with mains present, and the strict form would leave is_on_battery stuck true until the
        // next power event. recordPowerCut() keeps strict '<' on the restored column, so a
        // same-second pair converges on "restored" regardless of delivery order.
        $applied = DB::table('vends')
            ->where('id', $vend->id)
            ->where(function ($q) use ($at) {
                $q->whereNull('last_power_restored_at')->orWhere('last_power_restored_at', '<', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('last_power_cut_at')->orWhere('last_power_cut_at', '<=', $at);
            })
            ->update([
                'last_power_restored_at' => $at,
                'is_on_battery' => false,
                'updated_at' => $this->now(),
            ]);

        if ($applied) {
            $this->mergeJson($vend, [
                'power' => [
                    'state' => 'restored',
                    'at' => $at->toDateTimeString(),
                    'outage_seconds' => $this->outageSeconds($input),
                ],
            ]);
        }
    }

    /**
     * Keeps only the fields with a consumer, and coerces types the device may send loosely.
     * Unknown keys are dropped deliberately — every field stored is a field rewritten on the
     * `vends` row on every packet.
     */
    private function normaliseStatus(array $input): array
    {
        $locks = [];
        foreach ((array) ($input['locks'] ?? []) as $lock) {
            if (! is_array($lock)) {
                continue;
            }
            $locks[] = [
                'doorId' => (int) ($lock['doorId'] ?? 0),
                'lockState' => $this->str($lock['lockState'] ?? null),
                'doorState' => $this->str($lock['doorState'] ?? null),
                'lockOnlineState' => $this->str($lock['lockOnlineState'] ?? null),
            ];
        }

        $cameras = [];
        foreach ((array) ($input['cameras'] ?? []) as $camera) {
            if (! is_array($camera)) {
                continue;
            }
            $cameras[] = [
                'id' => (int) ($camera['id'] ?? $camera['cameraId'] ?? 0),
                'state' => $this->str($camera['state'] ?? $camera['cameraState'] ?? null),
            ];
        }

        // ABSENT keys are omitted from the patch entirely, not emitted as null/[]:
        // JSON_MERGE_PATCH deletes a key on null and replaces arrays wholesale, so a packet built
        // from one failed device read would otherwise ERASE last-known state (powerState gone,
        // cameras wiped to []) instead of leaving it stale — and stale-with-timestamp is strictly
        // more useful to ops than deleted. `status_at` always moves, so staleness is measurable.
        $status = [];

        if (isset($input['powerState'])) {
            // The host's Java enum constant name — 已连接 / 已断开 / 未知 (supplier-owned strings).
            $status['powerState'] = $this->str($input['powerState']);
        }
        if (isset($input['comprState'])) {
            $status['comprState'] = (bool) $input['comprState'];
        }
        if (isset($input['comprDuty2h'])) {
            $status['comprDuty2h'] = (int) $input['comprDuty2h'];
        }
        if (array_key_exists('locks', $input)) {
            $status['locks'] = $locks;
        }
        if (array_key_exists('cameras', $input)) {
            $status['cameras'] = $cameras;
        }

        return $status;
    }

    /**
     * One UPDATE, no read. Arrays inside the patch replace wholesale (JSON_MERGE_PATCH semantics),
     * which is what we want for `locks` / `cameras` / `errors`.
     */
    private function mergeJson(Vend $vend, array $patch): void
    {
        $encoded = json_encode($patch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        DB::update(
            'UPDATE vends
                SET freezer_status_json = JSON_MERGE_PATCH(COALESCE(freezer_status_json, JSON_OBJECT()), CAST(? AS JSON)),
                    updated_at = ?
              WHERE id = ?',
            [$encoded, $this->now(), $vend->id]
        );
    }

    /**
     * Device time when it is plausible, server time otherwise. An offline freezer has no NTP, so a
     * drifted clock must not write a timestamp that blocks every later event via the ordering guard.
     */
    private function eventTime(array $input): Carbon
    {
        $now = $this->now();
        $raw = $input['ts'] ?? null;

        if (! $raw) {
            return $now;
        }

        try {
            if (is_numeric($raw)) {
                $epoch = (int) $raw;

                // The APK sends System.currentTimeMillis() (milliseconds); accept seconds too so
                // the contract survives either side changing. Without this, a ms value parses to
                // year ~58000, fails the skew check, and EVERY event silently degrades to server
                // arrival time — defeating the ordering guards above.
                if ($epoch > self::EPOCH_MS_THRESHOLD) {
                    $epoch = intdiv($epoch, 1000);
                }

                // Carbon 3: with no tz argument this returns a UTC instance, which Laravel's
                // grammar renders as UTC wall clock — 8 h behind every other datetime we store
                // (app tz Asia/Singapore). Mixed UTC/SGT values in last_power_cut_at /
                // last_power_restored_at make the ordering guards above compare across zones and
                // silently drop genuine events for up to 8 h. Always pin the app timezone.
                $ts = Carbon::createFromTimestamp($epoch, config('app.timezone'));
            } else {
                $ts = Carbon::parse((string) $raw);
            }
        } catch (\Throwable) {
            return $now;
        }

        return abs($ts->diffInMinutes($now)) > self::MAX_CLOCK_SKEW_MINUTES ? $now : $ts;
    }

    private function outageSeconds(array $input): ?int
    {
        $seconds = $input['outage_seconds'] ?? null;

        return is_numeric($seconds) && $seconds >= 0 ? (int) $seconds : null;
    }

    private function str(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function now(): Carbon
    {
        return Carbon::now();
    }
}
