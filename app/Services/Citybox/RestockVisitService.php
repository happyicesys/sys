<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Exceptions\CityboxApiException;
use App\Jobs\SubmitCityboxCount;
use App\Models\CityboxDoorOpenLog;
use App\Models\CityboxInventoryPoll;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelRecord;
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
        private ChillerChannelMap $channelMap,
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

        // Channels are ours (2026-09-21); their API takes one count per PRODUCT, so a
        // SKU on two codes is summed. A code with no slot (mapping changed mid-visit)
        // is skipped, but a SKU their machine does not carry is reported: the driver
        // can load it, their AI cannot recognise it.
        $slots = $this->channelMap->forVend($vend);
        if ($slots === []) {
            $this->markSubmit($item, 'failed', 'This chiller has no product mapping — bind one before stocking in.');

            return false;
        }

        // On a mapping swap a changed slot has TWO rows for one code: the old product going
        // out (stock-in = -qty) and the new one coming in (is_upcoming_product). The slot now
        // belongs to the new product, so its row must win whatever order they load in —
        // otherwise the new SKU could be pushed as the old row's zero.
        $qtyByCode = [];
        foreach ($item->opsJobItemChannels->sortBy(fn ($ch) => (int) (bool) $ch->is_upcoming_product) as $ch) {
            $code = (int) $ch->vend_channel_code;
            if (! isset($slots[$code])) {
                continue;
            }
            // A row for a product the slot no longer holds says nothing about this SKU.
            if ((int) $ch->product_id !== $slots[$code]->productId) {
                continue;
            }
            $qtyByCode[$code] = $mode === 'revert'
                ? max(0, (int) $ch->actual_before_qty)
                : max(0, (int) $ch->actual_before_qty + (int) $ch->actual_qty);
        }
        // A SKU on two codes is ONE number to CityBox. If this item carries only some of
        // those codes (a facing added after the job was cut), the others keep what they
        // hold now — otherwise the push would silently erase their stock.
        $pushedSkus = [];
        foreach (array_keys($qtyByCode) as $code) {
            $pushedSkus[$slots[$code]->cityboxProductId] = true;
        }
        $siblings = array_filter($slots, fn ($slot) => ! isset($qtyByCode[$slot->code]) && isset($pushedSkus[$slot->cityboxProductId]));
        if ($siblings !== []) {
            $held = \App\Models\VendChannel::where('vend_id', $vend->id)->whereIn('code', array_keys($siblings))->pluck('qty', 'code');
            foreach ($siblings as $slot) {
                $qtyByCode[$slot->code] = max(0, (int) ($held[$slot->code] ?? 0));
            }
        }

        $counts = \App\Services\Citybox\ChillerChannelMap::sumBySku($slots, $qtyByCode);

        // Recognition check. A SKU their machine does not carry is LEFT OUT of the push
        // (what their API does with an unknown product is unproven) and reported; the
        // rest still goes, so one missing SKU never leaves the whole cabinet stale at
        // CityBox. If their config cannot be read, nothing is withheld — unknown is not
        // "missing", and an API blip must not strand the item in 'pending'.
        try {
            $unrecognisable = $this->stock->unrecognisableSlots($vend, fresh: true);
            $theirConfig = $this->stock->cachedTheirConfig($vend);
        } catch (\Throwable $e) {
            Log::warning('Citybox recognition check skipped — their config could not be read', ['vend_id' => $vend->id, 'error' => $e->getMessage()]);
            $unrecognisable = [];
            $theirConfig = [];
        }
        $withheld = array_values(array_filter($unrecognisable, fn ($m) => ($qtyByCode[$m['code']] ?? 0) > 0));
        foreach ($withheld as $m) {
            unset($counts[$m['citybox_product_id']]);
        }

        // Mapping swap: a SKU that LEFT the planogram was taken out by the driver
        // (enforceMappingSwapReturns returns it all), but no new slot speaks for it, so
        // CityBox would go on believing it is in the cabinet. Tell them zero — only for
        // SKUs their machine config actually carries.
        if ($mode === 'submit' && $item->stock_action_type === 'implement_new_mapping' && $theirConfig !== []) {
            $inNewLayout = array_flip(array_map(fn ($slot) => $slot->productId, $slots));
            $leaving = $item->opsJobItemChannels->pluck('product_id')->filter()->unique()
                ->reject(fn ($productId) => isset($inNewLayout[(int) $productId]))->all();
            if ($leaving !== []) {
                $ids = \App\Models\CityboxProduct::whereIn('product_id', $leaving)->where('is_delisted', false)->pluck('citybox_product_id');
                foreach ($ids as $cityboxId) {
                    if (isset($theirConfig[(int) $cityboxId]) && ! isset($counts[(int) $cityboxId])) {
                        $counts[(int) $cityboxId] = 0;
                    }
                }
            }
        }

        $withheldMessage = $withheld === [] ? null
            : 'Channel(s) '.implode(', ', array_column($withheld, 'code')).' hold a product this machine does not carry in CityBox — add it in OPS Pro (Pre-Stock Setup), then press Submit again. The other channels were pushed.';

        if ($counts === []) {
            $this->markSubmit($item, 'failed', $withheldMessage ?? 'No chiller channels on this item match the machine\'s mapping.');

            return false;
        }

        if ($mode === 'submit') {
            // Before Refill = CityBox's last minute-poll before the Stock In click,
            // captured before our submit overwrites it (Brian, 2026-09-03).
            $this->captureBefore($item, $vend, $slots);
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
            $this->clearAfter($item); // the After column described a stock-in that no longer stands
            Log::info('Citybox stock reverted to pre-restock count', ['ops_job_item_id' => $item->id, 'vend_id' => $vend->id, 'products' => count($counts)]);

            return true;
        }

        $this->markSubmit($item, $withheldMessage === null ? 'ok' : 'failed', $withheldMessage);
        // After Refill = one fresh pull now that CityBox has accepted the count — so
        // we can see whether their system reflects what we submitted. Also mirrored
        // onto the vend and pushed as the A frame (vend_channels qty).
        $this->captureAfter($item, $vend, $slots);
        Log::info('Citybox stock submitted', ['ops_job_item_id' => $item->id, 'vend_id' => $vend->id, 'products' => count($counts), 'withheld_codes' => array_column($withheld, 'code')]);

        return $withheldMessage === null;
    }

    /**
     * Before Refill for a chiller: the last CityBox minute-poll snapshot taken
     * before the Stock In click (never the door-open frame — Brian, 2026-09-03:
     * "before the latest stock-in click"). Written onto the item's own
     * vend_channel_records row (created here, linked by vend_channel_record_id)
     * so the ops page reads it like a VMC B frame, without the ±30 min matching.
     */
    private function captureBefore(OpsJobItem $item, Vend $vend, array $slots): void
    {
        try {
            $at = $item->completed_at ?? now();
            $poll = CityboxInventoryPoll::where('vend_id', $vend->id)
                ->whereNull('error')->where('polled_at', '<=', $at)
                ->orderByDesc('polled_at')->first();
            if (! $poll) {
                return;
            }
            $channels = $this->snapshotToChannels($poll->snapshot_json ?? [], $slots);
            $record = $this->itemRecord($item, $vend);
            $record->fill([
                'before_data_json' => ['channels' => $channels, 'label' => 'B', 'source' => 'citybox_poll', 'poll_id' => $poll->id],
                'before_data_created_at' => $poll->polled_at,
                'before_label' => 'B',
            ])->save();
            $this->writeVmcQty($item, $channels, 'vmc_before_qty');
        } catch (\Throwable $e) {
            Log::warning('Citybox before-refill capture failed', ['ops_job_item_id' => $item->id, 'error' => $e->getMessage()]);
        }
    }

    /** After Refill: fresh pull post-submit onto the item's record + vmc_after_qty; also the A frame. */
    private function captureAfter(OpsJobItem $item, Vend $vend, array $slots): void
    {
        try {
            $lines = $this->gateway->deviceStock((string) $vend->citybox_equipment_id);
            $this->stock->applyStockOnly($vend, $lines);
            $this->stock->pushChannels($vend, $lines, 'A', force: true);

            $qtyByCode = \App\Services\Citybox\ChillerChannelMap::allocate(
                $slots,
                $lines->mapWithKeys(fn ($l) => [(int) $l->cityboxProductId => (int) $l->quantity])->all(),
            );
            $channels = [];
            foreach ($slots as $code => $slot) {
                $channels[] = ['channel_code' => $slot->code, 'qty' => $qtyByCode[$slot->code] ?? 0, 'capacity' => $slot->capacity];
            }
            usort($channels, fn ($a, $b) => $a['channel_code'] <=> $b['channel_code']);
            $record = $this->itemRecord($item, $vend);
            $record->fill([
                'after_data_json' => ['channels' => $channels, 'label' => 'A', 'source' => 'citybox_pull'],
                'after_data_created_at' => now(),
                'after_label' => 'A',
            ])->save();
            $this->writeVmcQty($item, $channels, 'vmc_after_qty');
        } catch (\Throwable $e) {
            Log::warning('Citybox after-refill capture failed', ['ops_job_item_id' => $item->id, 'error' => $e->getMessage()]);
        }
    }

    private function clearAfter(OpsJobItem $item): void
    {
        try {
            if ($item->vend_channel_record_id) {
                VendChannelRecord::whereKey($item->vend_channel_record_id)->update(['after_data_json' => null, 'after_data_created_at' => null, 'after_label' => null]);
            }
            $item->opsJobItemChannels()->update(['vmc_after_qty' => null]);
        } catch (\Throwable $e) {
            Log::warning('Citybox after-refill clear failed', ['ops_job_item_id' => $item->id, 'error' => $e->getMessage()]);
        }
    }

    /** The item's own record (one per item, reused across retries / re-stock-ins). */
    private function itemRecord(OpsJobItem $item, Vend $vend): VendChannelRecord
    {
        $record = $item->vend_channel_record_id ? VendChannelRecord::find($item->vend_channel_record_id) : null;
        if (! $record) {
            $record = VendChannelRecord::create(['vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'operator_id' => $vend->operator_id]);
            $item->forceFill(['vend_channel_record_id' => $record->id])->saveQuietly();
        }

        return $record;
    }

    /** @return array<int,array{channel_code:int,qty:int,capacity:int}> */
    private function snapshotToChannels(array $snapshot, array $slots): array
    {
        $qtyByCode = \App\Services\Citybox\ChillerChannelMap::allocate(
            $slots,
            collect($slots)->mapWithKeys(fn ($slot) => [$slot->cityboxProductId => (int) ($snapshot['p'.$slot->cityboxProductId]['quantity'] ?? 0)])->all(),
        );
        $channels = [];
        foreach ($slots as $slot) {
            $channels[] = ['channel_code' => $slot->code, 'qty' => $qtyByCode[$slot->code] ?? 0, 'capacity' => $slot->capacity];
        }
        usort($channels, fn ($a, $b) => $a['channel_code'] <=> $b['channel_code']);

        return $channels;
    }

    private function writeVmcQty(OpsJobItem $item, array $channels, string $column): void
    {
        $byCode = collect($channels)->keyBy('channel_code');
        foreach ($item->opsJobItemChannels as $ch) {
            $line = $byCode->get((int) $ch->vend_channel_code);
            if ($line !== null) {
                $ch->forceFill([$column => $line['qty']])->saveQuietly();
            }
        }
    }

    /** Fresh pull + frame with a B/A label; failures logged, never thrown (the visit must not depend on it). */
    private function pushFrame(Vend $vend, string $label): void
    {
        try {
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
