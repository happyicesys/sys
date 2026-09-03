<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Exceptions\CityboxApiException;
use App\Jobs\SubmitCityboxCount;
use App\Models\CityboxDoorOpenLog;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use App\Services\Citybox\DTO\RestockSession;
use App\Services\Citybox\DTO\StockCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The ops visit against a chiller (design §6 / §6b / §6c):
 *
 *   openDoor()      D1 zyy_ls_open_door — every door-open in mark1 (Settings,
 *                   ops-job page, item page) goes through here: probe state,
 *                   open, WRITE AN AUDIT ROW (success or refusal), store the
 *                   latest msg_id on the ops item, then pull a fresh count and
 *                   dispatch the `B` (before) frame so vmc_before_qty is the
 *                   number the driver walked in to.
 *   submitCount()   D2 device_stock_submit at Stocked-In: the driver's
 *                   numerator (actual_before + actual_qty per channel) pushed
 *                   to CityBox; then pull → `A` frame. Never blocks the item:
 *                   failure = status 'failed' + retry job; success = 'ok'.
 *
 * Numerator/denominator, once: numerator = on-hand (read every 3 min, WRITTEN
 * by us here); denominator = their par (read-only capacity, never pushed).
 */
class RestockVisitService
{
    public const MAX_SUBMIT_ATTEMPTS = 5;

    public function __construct(
        private ChillerGateway $gateway,
        private StockPollService $stock,
    ) {}

    /**
     * Open the door. Throws CityboxApiException on refusal — after logging it.
     *
     * @param  Vend|OpsJobItem  $target  an item ties the open to the visit; a bare vend is the Settings page
     */
    public function openDoor(Vend|OpsJobItem $target, ?User $by, string $source = CityboxDoorOpenLog::SOURCE_VEND_SETTINGS, ?Request $request = null): RestockSession
    {
        [$vend, $item] = $target instanceof OpsJobItem ? [$target->vend, $target] : [$target, null];
        $equipmentId = (string) $vend->citybox_equipment_id;

        $stateBefore = null;
        try {
            $stateBefore = $this->gateway->deviceState($equipmentId)->value;
        } catch (\Throwable) {
            // best-effort — an offline device 400s here; the open itself will say so
        }

        $log = [
            'vend_id' => $vend->id, 'citybox_equipment_id' => $equipmentId,
            'ops_job_item_id' => $item?->id, 'ops_job_id' => $item?->ops_job_id,
            'user_id' => $by?->id, 'source' => $source, 'requested_at' => now(),
            'device_state_before' => $stateBefore,
            'ip' => $request?->ip(), 'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
        ];

        try {
            $session = $this->gateway->openForRestock($equipmentId, 'mark1-u'.($by?->id ?? 0));
        } catch (CityboxApiException $e) {
            CityboxDoorOpenLog::create($log + [
                'result' => CityboxDoorOpenLog::RESULT_REFUSED,
                'citybox_code' => (string) $e->apiCode, 'citybox_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        CityboxDoorOpenLog::create($log + [
            'result' => CityboxDoorOpenLog::RESULT_OPENED,
            'msg_id' => $session->msgId, 'open_log_id' => $session->openLogId,
        ]);

        if ($item) {
            // Latest open wins: a driver may open twice in one visit; the submit must use the newest msg_id.
            $item->forceFill(['citybox_msg_id' => $session->msgId])->save();
        }
        // Re-arm (Brian, 2026-09-03): the door stays openable after Stocked-In so a
        // driver can rearrange goods — every open is logged above. If the count push
        // had failed for want of a session, this open supplies one: queue it again.
        $rearm = $item && $item->citybox_submit_status === 'failed' && (int) $item->status >= (int) OpsJob::STATUS_DELIVERED;
        if ($rearm) {
            $item->forceFill(['citybox_submit_status' => 'pending', 'citybox_submit_error' => null])->saveQuietly();
            SubmitCityboxCount::dispatch($item->id)->onQueue('high');
        }
        // Stopgap mirror for the Settings page (kept until phase-3 cleanup).
        $status = $vend->citybox_status_json ?? [];
        $status['last_ops_open'] = ['msg_id' => $session->msgId, 'open_log_id' => $session->openLogId, 'user_id' => $by?->id, 'source' => $source, 'at' => $session->openedAt->toDateTimeString()];
        $vend->forceFill(['citybox_status_json' => $status])->save();

        // B frame: fresh count of what the driver walked in to (§6c.2). Planogram
        // re-mirrored too, so a portal edit made this morning is reflected. Not on
        // a post-Stocked-In open: the goods are already in, so that count is not
        // a "before" and would overwrite the visit's real one.
        if (! $item || (int) $item->status < (int) OpsJob::STATUS_DELIVERED) {
            $this->pushFrame($vend, 'B');
        }

        Log::info('Citybox door opened (ops)', ['vend_id' => $vend->id, 'ops_job_item_id' => $item?->id, 'user_id' => $by?->id, 'source' => $source, 'msg_id' => $session->msgId]);

        return $session;
    }

    /**
     * Push the driver's count for a Stocked-In item. Called by the queued
     * SubmitCityboxCount job (which retries). Returns true on success.
     * Reads ops_job_item_channels (actual_before_qty + actual_qty) — the
     * numbers the driver keyed — mapped back to CityBox product ids through
     * the vend's planogram codes.
     */
    public function submitCount(OpsJobItem $item): bool
    {
        return $this->pushCounts($item, 'submit');
    }

    /**
     * Undo Stock In (Brian, 2026-09-03): put CityBox back to what the driver
     * walked in to — actual_before_qty per channel — so their portal matches
     * mark1 at every step. Same session, same endpoint; status 'reverted' on
     * success. A later Stock In pushes the fresh count again via the observer.
     */
    public function revertCount(OpsJobItem $item): bool
    {
        return $this->pushCounts($item, 'revert');
    }

    private function pushCounts(OpsJobItem $item, string $mode): bool
    {
        $vend = $item->vend;
        if (! $vend || $vend->machine_type !== Vend::MACHINE_TYPE_SMART_CHILLER || ! $vend->citybox_equipment_id) {
            return false;
        }
        // Session: the item's own open, else the chiller's latest successful open
        // from ANY source (Settings page, job page). Brian, 2026-09-03: the push
        // must not depend on the driver opening from the item page — a door opened
        // any other way is the same physical visit. Stored on the item so retries
        // reuse it; if CityBox rejects a stale one, their message lands in the banner.
        if (! $item->citybox_msg_id) {
            $latest = CityboxDoorOpenLog::where('vend_id', $vend->id)
                ->where('result', CityboxDoorOpenLog::RESULT_OPENED)
                ->whereNotNull('msg_id')
                ->latest('requested_at')->first();
            if ($latest) {
                $item->forceFill(['citybox_msg_id' => $latest->msg_id])->saveQuietly();
                Log::info('Citybox stock submit using the chiller\'s latest door-open session', ['ops_job_item_id' => $item->id, 'door_open_log_id' => $latest->id, 'source' => $latest->source, 'opened_at' => (string) $latest->requested_at]);
            }
        }
        if (! $item->citybox_msg_id) {
            $this->markSubmit($item, 'failed', 'No door-open session (msg_id) for this chiller — press Open Door; the push re-runs on its own.');

            return false;
        }

        $codes = $this->stock->refreshPlanogram($vend); // citybox_product_id => [code, par, layer]
        $byCode = [];
        foreach ($codes as $cid => $c) {
            $byCode[$c['code']] = (int) $cid;
        }

        $counts = [];
        foreach ($item->opsJobItemChannels as $ch) {
            $cid = $byCode[(int) $ch->vend_channel_code] ?? null;
            if ($cid === null) {
                continue; // channel not in the current planogram — nothing to tell CityBox
            }
            $counts[$cid] = $mode === 'revert'
                ? max(0, (int) $ch->actual_before_qty)
                : max(0, (int) $ch->actual_before_qty + (int) $ch->actual_qty);
        }
        if ($counts === []) {
            $this->markSubmit($item, 'failed', 'No chiller channels on this item map to the CityBox planogram.');

            return false;
        }

        try {
            $session = new RestockSession((string) $vend->citybox_equipment_id, $item->citybox_msg_id, '', now()->toImmutable());
            $this->gateway->submitCount($session, StockCount::of($counts));
        } catch (\Throwable $e) {
            $this->markSubmit($item, 'failed', ($mode === 'revert' ? 'Revert: ' : '').$e->getMessage());

            return false;
        }

        if ($mode === 'revert') {
            $this->markSubmit($item, 'reverted', null);
            Log::info('Citybox stock reverted to pre-restock count', ['ops_job_item_id' => $item->id, 'vend_id' => $vend->id, 'products' => count($counts)]);

            return true;
        }

        $this->markSubmit($item, 'ok', null);
        // A frame: CityBox's stock as it now stands (their camera after our submit).
        $this->pushFrame($vend, 'A');
        Log::info('Citybox stock submitted', ['ops_job_item_id' => $item->id, 'vend_id' => $vend->id, 'products' => count($counts)]);

        return true;
    }

    /** Fresh pull + frame with a B/A label; failures logged, never thrown (the visit must not depend on it). */
    private function pushFrame(Vend $vend, string $label): void
    {
        try {
            $this->stock->refreshPlanogram($vend);
            $lines = $this->gateway->deviceStock((string) $vend->citybox_equipment_id);
            $this->stock->applyStockOnly($vend, $lines);
            $this->stock->pushChannels($vend, $lines, $label, force: true);
        } catch (\Throwable $e) {
            Log::warning("Citybox {$label}-frame pull failed", ['vend_id' => $vend->id, 'error' => $e->getMessage()]);
        }
    }

    private function markSubmit(OpsJobItem $item, string $status, ?string $error): void
    {
        $item->forceFill([
            'citybox_submit_status' => $status,
            'citybox_submitted_at' => in_array($status, ['ok', 'reverted'], true) ? now() : $item->citybox_submitted_at,
            'citybox_submit_attempts' => $item->citybox_submit_attempts + 1,
            'citybox_submit_error' => $error,
        ])->saveQuietly(); // quietly: don't re-trigger the observer
    }
}
