<?php

namespace App\Services;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannel;

class ProductMappingService
{
    public function syncChannels($productMappingID)
    {
        $productMapping = ProductMapping::findOrFail($productMappingID);

        if ($productMapping->vends()->exists()) {
            foreach ($productMapping->vends as $vend) {
                // A Smart Freezer has no board frame to create its channels, so the loop below —
                // which only ever UPDATES rows that already exist — never reached one. Its channels
                // are written from this planogram instead (FreezerChannelSync), then the same
                // product/price pass runs over them via SyncVendChannels.
                if ($vend->isSmartFreezer()) {
                    app(\App\Services\Freezer\FreezerChannelSync::class)->sync($vend);

                    continue;
                }
                // A chiller's rows are derived from the mapping plus CityBox's live stock; the
                // generic loop below would null every product_id until the next poll. Rebuild
                // from a fresh pull instead (best-effort, logged; the 3-min poll does the same).
                if ($vend->isSmartChiller()) {
                    app(\App\Services\Citybox\StockPollService::class)->rebuildChannels($vend);

                    continue;
                }

                if ($vend->vendChannels()->exists()) {
                    $vend->vendChannels()->update(['product_id' => null]);

                    if ($productMapping->productMappingItems()->exists()) {
                        foreach ($productMapping->productMappingItems as $productMappingItem) {
                            $vendChannel = $vend->vendChannels()->where('code', (int) $productMappingItem->channel_code)->first();
                            if ($vendChannel) {
                                $vendChannel->product_id = $productMappingItem->product_id;
                                if ($productMapping->selling_price_type) {
                                    $sellingPrice = SellingPrice::where('product_id', $productMappingItem->product_id)->where('type', $productMapping->selling_price_type)->first();
                                    if ($sellingPrice) {
                                        $productMappingItem->update(['server_amount' => $sellingPrice->amount]);
                                    }
                                }
                                $vendChannel->save();
                            }
                        }
                    }
                    SaveVendChannelsJson::dispatch($vend->id)->onQueue('high');
                }
            }
        }
    }

    public function syncSingleChannel($vendChannelID, $productMappingItemID)
    {
        $vendChannel = VendChannel::findOrFail($vendChannelID);
        $productMappingItem = ProductMappingItem::findOrFail($productMappingItemID);

        $vendChannel->update(['product_id' => null]);

        if ($vendChannel and $productMappingItem) {
            $vendChannel->product_id = $productMappingItem->product_id;
            $vendChannel->save();
            SaveVendChannelsJson::dispatch($vendChannel->vend_id)->onQueue('high');
        }
    }

    public function syncChannel($vendChannelID)
    {
        // Find the specific vendChannel instance
        $vendChannel = VendChannel::findOrFail($vendChannelID);

        // Access the associated Vend and ProductMapping
        $vend = $vendChannel->vend;
        $productMapping = $vend->productMapping;

        // Clear the product_id for the specific vendChannel
        $vendChannel->product_id = null;

        if ($productMapping) {
            // Find the associated ProductMappingItem using channel_code
            $productMappingItem = $productMapping->productMappingItems()
                ->where('channel_code', (int) $vendChannel->code)
                ->first();

            if ($productMappingItem) {
                // Update vendChannel's product_id with the associated product ID
                $vendChannel->product_id = $productMappingItem->product_id;

                // If a selling price type is defined, update server_amount
                if ($productMapping->selling_price_type) {
                    $sellingPrice = SellingPrice::where('product_id', $productMappingItem->product_id)
                        ->where('type', $productMapping->selling_price_type)
                        ->first();

                    if ($sellingPrice) {
                        $productMappingItem->update(['server_amount' => $sellingPrice->amount]);
                    }
                }

                // Dispatch a job to update VendChannels JSON
                SaveVendChannelsJson::dispatch($vend->id)->onQueue('high');
            }
        }

        $vendChannel->save();

    }

    public function syncChannelsByVend(Vend $vend)
    {
        // A SKU-stocked machine's rows carry their product as identity, written by
        // FreezerChannelSync / ChannelFrameAdapter through SyncVendChannels. Nulling
        // and re-assigning product_id by code here would destroy that identity
        // (and two SKUs may share one code).
        if ($vend->isSkuStocked()) {
            return;
        }
        $productMapping = $vend->productMapping;
        if ($productMapping) {
            $vendChannels = $vend->vendChannels()->where('is_active', true)->get();
            $productMappingItems = $productMapping->productMappingItems->keyBy(function ($item) {
                return (int) $item->channel_code;
            });

            $sellingPrices = collect();
            if ($productMapping->selling_price_type) {
                $productIds = $productMappingItems->pluck('product_id')->filter()->unique()->toArray();
                $sellingPrices = SellingPrice::whereIn('product_id', $productIds)
                    ->where('type', $productMapping->selling_price_type)
                    ->get()
                    ->keyBy('product_id');
            }

            foreach ($vendChannels as $vendChannel) {
                $originalProductId = $vendChannel->product_id;
                $vendChannel->product_id = null;

                $productMappingItem = $productMappingItems->get((int) $vendChannel->code);

                if ($productMappingItem) {
                    $vendChannel->product_id = $productMappingItem->product_id;

                    if ($productMapping->selling_price_type) {
                        $sellingPrice = $sellingPrices->get($productMappingItem->product_id);
                        if ($sellingPrice && $productMappingItem->server_amount != $sellingPrice->amount) {
                            $productMappingItem->update(['server_amount' => $sellingPrice->amount]);
                        }
                    }
                }

                if ($vendChannel->product_id !== $originalProductId) {
                    $vendChannel->save();
                }
            }
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('high');
        }
    }
}
