<?php

namespace App\Services\Freezer;

use App\Jobs\Vend\SyncVendChannels;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannel;

/**
 * Writes a Smart Freezer's `vend_channels` from its planogram.
 *
 * A vending machine's channels arrive from the board's own CHANNEL frame; a freezer never sends one
 * (its APK treats CHANNEL purely as a "re-pull your menu" nudge), so before this existed a freezer
 * had no channel rows at all — the Operation Dashboard's channel cell was blank and nothing could
 * carry its stock. Channel config for these machines is top-down from mark1 (estate rule), so the
 * bound ProductMapping is the source: one row per mapping item, code `<basket><division>`.
 *
 * Two things this deliberately does NOT own:
 *  - **qty.** It is our ledger (ops-job topup in, each sale out), not a supplier feed, so an existing
 *    row keeps its quantity through every re-sync. A new row starts empty.
 *  - **capacity.** Nobody has measured a basket yet; `config('smart_freezer.channel_capacity')` is
 *    the placeholder par, and an existing row's capacity is kept in case ops has set a real one.
 *    It has to be > 0 or SyncVendChannels files the channel as inactive and it disappears again.
 *
 * Prices are the Site's RP tier, which is what a freezer sells at — it has no board price
 * (Vend::serverPriceType). A slot that has left the planogram is sent with capacity 0, which
 * deactivates it rather than leaving a sold-out ghost on the dashboard.
 */
class FreezerChannelSync
{
    /**
     * @return int the number of planogram slots pushed, 0 when this vend is not a freezer with a mapping
     */
    public function sync(Vend $vend): int
    {
        if (! $vend->isSmartFreezer()) {
            return 0;
        }

        $mapping = $vend->productMapping()->with('productMappingItems')->first();
        if (! $mapping) {
            return 0;
        }

        $existing = VendChannel::where('vend_id', $vend->id)->get()->keyBy(fn ($row) => (int) $row->code);
        $defaultCapacity = (int) config('smart_freezer.channel_capacity', 20);
        $prices = $this->serverPrices($vend, $mapping->productMappingItems->pluck('product_id')->filter()->unique()->all());

        $channels = [];
        $seen = [];
        foreach ($mapping->productMappingItems as $item) {
            $code = (int) $item->channel_code;
            if ($code <= 0) {
                continue;
            }
            $seen[] = $code;
            $row = $existing->get($code);
            $channels[] = [
                'channel_code' => $code,
                'qty' => (int) ($row?->qty ?? 0),
                'capacity' => (int) ($row?->capacity ?: $defaultCapacity),
                'amount' => (int) ($prices[$item->product_id] ?? $item->getRawOriginal('server_amount') ?? 0),
                'amount2' => 0,
                'error_code' => 0,
            ];
        }

        // Slots that left the planogram: capacity 0 retires them (SyncVendChannels::getVendChannelStatus).
        foreach ($existing as $code => $row) {
            if (! in_array($code, $seen, true) && $row->is_active) {
                $channels[] = ['channel_code' => $code, 'qty' => 0, 'capacity' => 0, 'amount' => 0, 'amount2' => 0, 'error_code' => 0];
            }
        }

        if ($channels === []) {
            return 0;
        }

        // Same path a machine's own frame takes, so the channel rows, the vend_channels_json the
        // dashboard reads, the delivery-platform mirror and the error-rate columns all stay in step.
        SyncVendChannels::dispatch(['channels' => $channels], $vend)->onQueue('high');

        return count($seen);
    }

    /**
     * Site-tier selling price per product, in cents. Empty when the machine has no Site or its
     * pricing source is off — then the planogram item's own server_amount is the fallback.
     *
     * @param  array<int,int>  $productIds
     * @return array<int,int>
     */
    private function serverPrices(Vend $vend, array $productIds): array
    {
        $type = $vend->serverPriceType();
        if (! $type || $productIds === []) {
            return [];
        }

        return SellingPrice::whereIn('product_id', $productIds)
            ->where('type', $type)
            ->get()
            ->mapWithKeys(fn ($price) => [$price->product_id => (int) $price->getRawOriginal('amount')])
            ->all();
    }
}
