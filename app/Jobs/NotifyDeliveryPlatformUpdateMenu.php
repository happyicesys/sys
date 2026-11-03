<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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

    public static function debounced(DeliveryProductMappingVend $deliveryProductMappingVend): void
    {
        static::dispatch($deliveryProductMappingVend)
            ->onQueue('default')
            ->delay(now()->addSeconds(config('grab.menu_notify_delay_seconds', 180)));
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
        $mappingVend = $this->deliveryProductMappingVend->fresh();

        if (! $mappingVend || ! $mappingVend->isSelling()) {
            return;
        }

        // Grab answered "merchant not be integrated yet" (403) in the last day: the store is
        // not live on Grab's side (ARK 4-C73WV2JKAFCABE, 52 refusals 10-30..11-01); asking
        // on every change only adds refusals. Asked again after a day.
        $merchantId = $deliveryPlatformService->merchantIdFromMappingVend($mappingVend);
        if ($merchantId && DeliveryPlatformApiError::refusedRecently($merchantId, [403])) {
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
            return;
        }

        $response = $deliveryPlatformService->notifyUpdatedMenu($mappingVend);

        if (($response['success'] ?? false) && $merchantId) {
            Cache::put(self::lastNotifyKey($merchantId), now()->getTimestamp(), self::SECONDS_BETWEEN_NOTIFIES);
        }

        // 409 "sync menu too frequently, retry after 310 seconds": Grab allows one menu sync
        // per store about every 5 min. Ask again once that time is up (59 such refusals on
        // 26 stores 10-30..11-01 were dropped, so the change waited for the next one).
        if (($response['code'] ?? null) === 409 && $this->retries < self::MAX_RETRIES) {
            $wait = self::retryAfterSeconds($response) ?? self::DEFAULT_RETRY_SECONDS;
            static::dispatch($mappingVend, $this->force, $this->retries + 1)
                ->onQueue('default')
                ->delay(now()->addSeconds($wait + 15));
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
