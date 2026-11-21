<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\Menu\GrabMenuSyncTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Tells Grab to re-pull one store's menu.
 *
 * Every VMC CHANNEL frame used to send this at once (~750 full re-syncs a day over
 * ~70 stores, 2026-10), against Grab's own rule of a full sync about once a day.
 * Now a change is sent through debounced(): the job waits
 * grab.menu_notify_delay_seconds, changes made while it waits ride along (one job
 * per store), and it sends nothing when the menu Grab would pull equals the one it
 * pulled last. A store mark1 does not sell (paused or unbound) is never notified,
 * forced or not: its menu pull is refused.
 *
 * Every step is reported to GrabMenuSyncTracker (Grab Menu Sync page, 2026-11-21). Grab's 429 "too many
 * requests", a 5xx or no answer is asked again with a growing wait (2026-11-21: 30 + 16 stores of two
 * price-rule saves were turned away with 429 and never asked again).
 */
class NotifyDeliveryPlatformUpdateMenu implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Kept so a job serialized before 2026-10-29 still unserializes; no longer set.
    protected $deliveryPlatformService;

    protected $deliveryProductMappingVend;

    // true = the daily full sync: send even when nothing changed.
    protected bool $force;

    // A 409 "too frequently" is asked again at most this many times.
    const MAX_RETRIES = 3;

    const DEFAULT_RETRY_SECONDS = 310;

    // A 429, a 5xx or no answer is asked again at most this many times: 1, 2, 4, 8, 15 min (+ up to 30 s).
    const MAX_BUSY_RETRIES = 5;

    // Gap kept after a notify Grab took. Its lock is 310 s; on 2026-11-03 no store was
    // pulled twice inside 317 s, and all 20 of that day's 409s came 3-5 min after a pull.
    const SECONDS_BETWEEN_NOTIFIES = 325;

    // How many times this notify was put back after a 409.
    protected int $retries = 0;

    public function __construct(DeliveryProductMappingVend $deliveryProductMappingVend, bool $force = false, int $retries = 0)
    {
        $this->deliveryProductMappingVend = $deliveryProductMappingVend;
        $this->force = $force;
        $this->retries = $retries;
    }

    /**
     * $spreadSeconds pushes this store's ask later than the debounce: a change reaching many stores spaces them
     * out (grab.daily_sync_spacing_seconds apart) instead of asking Grab for all at the same second.
     */
    public static function debounced(DeliveryProductMappingVend $deliveryProductMappingVend, int $spreadSeconds = 0): void
    {
        $place = app(GrabMenuSyncTracker::class)->queued($deliveryProductMappingVend);
        if ($place !== null) {
            $spreadSeconds = max($spreadSeconds, $place * (int) config('grab.daily_sync_spacing_seconds', 3));
        }

        static::dispatch($deliveryProductMappingVend)
            ->onQueue('default')
            ->delay(now()->addSeconds(config('grab.menu_notify_delay_seconds', 180) + max(0, $spreadSeconds)));
    }

    public function uniqueId(): string
    {
        return $this->deliveryProductMappingVend->id.($this->force ? ':force' : '');
    }

    public function uniqueFor(): int
    {
        return (int) config('grab.menu_notify_delay_seconds', 180) + 600;
    }

    public function handle(DeliveryPlatformService $deliveryPlatformService): void
    {
        $tracker = app(GrabMenuSyncTracker::class);
        $mappingVend = $this->deliveryProductMappingVend->fresh();

        if (! $mappingVend) {
            return;
        }

        if (! $mappingVend->isSelling()) {
            $tracker->skipped($mappingVend, 'The store does not sell on Grab now (paused, switched off or not on a machine)');

            return;
        }

        // Grab answered "merchant not be integrated yet" (403) in the last day: the store is
        // not live on Grab's side (ARK 4-C73WV2JKAFCABE, 52 refusals 10-30..11-01); asking
        // on every change only adds refusals. Asked again after a day.
        $merchantId = $deliveryPlatformService->merchantIdFromMappingVend($mappingVend);
        if ($merchantId && DeliveryPlatformApiError::refusedRecently($merchantId, [403])) {
            $tracker->refused($mappingVend, 403, 'Grab refuses this store (not integrated on Grab); not asked for a day');

            return;
        }

        // Still inside Grab's lock from our last notify (the 180 s debounce is shorter): come
        // back when it ends instead of drawing a 409. Changes made meanwhile ride along.
        $wait = self::secondsUntilGrabAllows($merchantId);
        if ($wait > 0) {
            static::dispatch($mappingVend, $this->force, $this->retries)
                ->onQueue('default')
                ->delay(now()->addSeconds($wait));

            return;
        }

        if (! $this->force && ! $deliveryPlatformService->menuChangedSinceLastPull($mappingVend)) {
            $tracker->alreadyOnGrab($mappingVend);

            return;
        }

        // Built before asking: Grab pulls within seconds, and the record of what was sent must be in first.
        $menu = $this->menuFor($deliveryPlatformService, $mappingVend);

        try {
            $response = $deliveryPlatformService->notifyUpdatedMenu($mappingVend);
        } catch (ConnectionException $e) {
            $response = ['success' => false, 'code' => null, 'message' => 'No answer from Grab: '.$e->getMessage()];
        }

        if ($response['success'] ?? false) {
            if ($merchantId) {
                Cache::put(self::lastNotifyKey($merchantId), now()->getTimestamp(), self::SECONDS_BETWEEN_NOTIFIES);
            }
            $tracker->sent($mappingVend, $menu);

            return;
        }

        if ($response === null) {
            return;
        }

        $code = $response['code'] ?? null;

        // 409 "sync menu too frequently, retry after 310 seconds": Grab allows one menu sync
        // per store about every 5 min. Ask again once that time is up (59 such refusals on
        // 26 stores 10-30..11-01 were dropped, so the change waited for the next one).
        if ($code === 409 && $this->retries < self::MAX_RETRIES) {
            $wait = (self::retryAfterSeconds($response) ?? self::DEFAULT_RETRY_SECONDS) + 15;
            static::dispatch($mappingVend, $this->force, $this->retries + 1)
                ->onQueue('default')
                ->delay(now()->addSeconds($wait));
            $tracker->turnedAway($mappingVend, 409, self::grabSaid($response, 'Grab: menu synced too often, asked again after its wait'), now()->addSeconds($wait));

            return;
        }

        // 429 "too many requests", a Grab server error or no answer: Grab is busy, not refusing this store.
        if (self::isBusy($code) && $this->retries < self::MAX_BUSY_RETRIES) {
            $wait = min(60 * (2 ** $this->retries), 900) + random_int(0, 30);
            static::dispatch($mappingVend, $this->force, $this->retries + 1)
                ->onQueue('default')
                ->delay(now()->addSeconds($wait));
            $tracker->turnedAway($mappingVend, $code, self::grabSaid($response, $code === 429 ? 'Grab: too many requests' : 'Grab did not answer'), now()->addSeconds($wait));

            return;
        }

        $tracker->turnedAway($mappingVend, $code, self::grabSaid($response, 'Grab refused').' — gave up after '.($this->retries + 1).' asks', null);
    }

    public static function isBusy(?int $code): bool
    {
        return $code === null || $code === 429 || $code >= 500;
    }

    // Grab's own words from a refused answer, else $fallback.
    private static function grabSaid(array $response, string $fallback): string
    {
        $said = trim((string) ($response['data']['message'] ?? $response['data']['reason'] ?? ''));

        return $said !== '' ? $fallback.' ('.$said.')' : $fallback;
    }

    // The menu Grab is about to pull, for the record (built again: the changed-check above is skipped when forced).
    private function menuFor(DeliveryPlatformService $deliveryPlatformService, DeliveryProductMappingVend $mappingVend): ?array
    {
        try {
            return $deliveryPlatformService->buildMenu($mappingVend);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function secondsUntilGrabAllows(?string $merchantId): int
    {
        $lastNotify = $merchantId ? Cache::get(self::lastNotifyKey($merchantId)) : null;

        return $lastNotify ? max(0, (int) $lastNotify + self::SECONDS_BETWEEN_NOTIFIES - now()->getTimestamp()) : 0;
    }

    private static function lastNotifyKey(string $merchantId): string
    {
        return 'grab-menu-last-notify:'.$merchantId;
    }

    // "retry after 310 seconds" in Grab's 409 message, as seconds.
    public static function retryAfterSeconds(?array $response): ?int
    {
        $message = (string) ($response['data']['message'] ?? '');

        return preg_match('/retry after (\d+) seconds?/i', $message, $match) ? (int) $match[1] : null;
    }
}
