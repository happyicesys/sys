<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Models\ProductMapping;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SmartFreezerCatalogPush — nudges bound Smart-Freezer terminals to re-pull their
 * menu after a planogram edit in mark1.
 *
 * WHY THIS EXISTS
 * ---------------
 * The freezer APK (`sg.mark1.freezer`) does NOT poll `GET /api/vends/{code}/menu`
 * on a timer. It fetches the menu exactly twice: once per boot (after the
 * mqtt-config bootstrap discovers the mark1 host), and again whenever it receives
 * a "config changed / catalog" push over MQTT. So a planogram saved in mark1 was
 * invisible to a machine that had been up for a while until someone pressed
 * "Push Products Info to Machine" on the Vend page or restarted the terminal.
 *
 * WHAT IT SENDS
 * -------------
 * The same signed CSV envelope every other VMC/APK command uses:
 *
 *     <fid>,<contentLength>,<base64(json)>,<md5(fid,len,content . private_key)>
 *
 * with `Type = TYPESYNCAPICHANNELSLOTLIST`, published QoS-1 to topic `CM{vend_code}`.
 * The APK's PushMessageParser maps that Type to ConfigChanged(CATALOG), and its
 * PushCoordinator then revalidates the catalogue over HTTP — the frame itself
 * carries NO product data, it is a content-free "go re-pull" signal. That keeps
 * the payload off MQTT (metered 4G) and means one nudge picks up every change
 * already committed for that mapping, whatever endpoint wrote it.
 *
 * EVERY planogram write path is hooked, not just Save — the smart grid
 * (SmartFreezerLayout.vue) commits bind / unbind / drag-reorder immediately through
 * createItem / deleteItem / reorderBasket and page-level Save is optional, so a
 * Save-only hook would leave a freezer stale whenever the operator closed the tab.
 * bindVends() nudges BOTH sides: the machines now on the mapping (via
 * pushForMapping) and the machines just unbound from it (via pushForVendIds) —
 * an unbound machine's menu goes empty and it has to be told.
 *
 * Callers MUST push AFTER the surrounding DB transaction commits. The queue is
 * redis, so a worker can pick the job up within milliseconds; nudging from inside
 * an open transaction races the commit and the device can re-pull the OLD menu.
 *
 * The APK conflates + debounces nudges (~5s), so a burst of rapid saves collapses
 * into a single menu download on the device.
 *
 * BLAST RADIUS
 * ------------
 * Targets are scoped with `Vend::scopeSmart()` (vend_model = "Smart Vend") and
 * `is_active`, i.e. the runtime gate — NOT `product_mappings.is_smart`. A legacy
 * vending mapping therefore resolves to an empty target set and publishes nothing;
 * the existing fleet's behaviour is bit-for-bit unchanged.
 *
 * Publishing is fire-and-forget through the `high` queue and is wrapped so that a
 * broker/queue failure can never fail the save that triggered it — the planogram
 * is already committed at that point, and the operator can always fall back to the
 * manual "Push Products Info to Machine" button.
 *
 * @see \App\Http\Controllers\ProductMappingController::update()
 * @see \App\Http\Controllers\VendController::syncVendChannels()  identical frame, manual trigger
 */
class SmartFreezerCatalogPush
{
    /** MQTT command Type the freezer APK maps to ConfigChanged(CATALOG). */
    public const FRAME_TYPE = 'TYPESYNCAPICHANNELSLOTLIST';

    /** Frame id — 1 for every server→device command frame in this protocol. */
    private const FRAME_ID = 1;

    /** Signing key used when a vend row has no `private_key` (matches the rest of mark1). */
    private const FALLBACK_FRAME_KEY = '123456789110138A';

    /**
     * Nudges every active Smart-Freezer bound to this mapping.
     *
     * Returns BOTH counts on purpose. Collapsing them into one number makes a
     * total dispatch failure (queue backend down => pushed 0 of 3) look exactly
     * like the ordinary no-op (vending mapping => pushed 0 of 0), so the operator
     * would be told nothing at all in the one case where they need to know to fall
     * back to the manual "Push Products Info to Machine" button.
     *
     * @return array{targets:int, pushed:int} targets = active smart freezers bound
     *                                        to this mapping; pushed = nudges queued
     */
    public function pushForMapping(ProductMapping $productMapping): array
    {
        return $this->pushQuery($productMapping->vends());
    }

    /**
     * Nudges an explicit set of machines by id — for writes that move a machine OFF
     * a mapping (bindVends), where the target is by definition no longer reachable
     * through {@see pushForMapping}. Its menu just went empty; it has to be told.
     *
     * @param  array<int|string>  $vendIds
     * @return array{targets:int, pushed:int}
     */
    public function pushForVendIds(array $vendIds): array
    {
        if (empty($vendIds)) {
            return ['targets' => 0, 'pushed' => 0];
        }

        return $this->pushQuery(Vend::whereIn('id', $vendIds));
    }

    /**
     * Applies the smart + active filter to a vend query and publishes to whatever
     * survives. Filtering in SQL (not in PHP over a loaded collection) is what keeps
     * a legacy vending mapping at literally zero publishes and zero N+1.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @return array{targets:int, pushed:int}
     */
    private function pushQuery($query): array
    {
        $vends = $query->smart()->where('is_active', true)->get();

        $pushed = 0;
        foreach ($vends as $vend) {
            if ($this->pushToVend($vend)) {
                $pushed++;
            }
        }

        return ['targets' => $vends->count(), 'pushed' => $pushed];
    }

    /**
     * Queues one catalog-refresh nudge for one machine.
     *
     * Never throws: a failure here must not roll back or 500 a save that has
     * already been committed.
     *
     * @return bool true when the job was queued
     */
    public function pushToVend(Vend $vend): bool
    {
        try {
            PublishMqtt::dispatch('CM' . $vend->code, $this->frameFor($vend))->onQueue('high');

            return true;
        } catch (\Throwable $e) {
            Log::warning('SmartFreezerCatalogPush: failed to queue catalog nudge', [
                'vend_code' => $vend->code,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Builds the signed CSV envelope for one machine.
     *
     * Byte-for-byte the same construction as VendController::syncVendChannels() so
     * the device cannot tell an automatic nudge from a manual one.
     */
    private function frameFor(Vend $vend): string
    {
        $content = base64_encode(json_encode([
            'Type' => self::FRAME_TYPE,
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend->private_key ?: self::FALLBACK_FRAME_KEY;
        $md5 = md5(self::FRAME_ID . ',' . $contentLength . ',' . $content . $key);

        return self::FRAME_ID . ',' . $contentLength . ',' . $content . ',' . $md5;
    }
}
