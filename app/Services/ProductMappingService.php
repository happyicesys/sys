<?php

namespace App\Services;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannel;
use Carbon\Carbon;

class ProductMappingService
{
    public function syncChannels($productMappingID)
    {
        $productMapping = ProductMapping::findOrFail($productMappingID);

        if($productMapping->vends()->exists()) {
            foreach($productMapping->vends as $vend) {
                if($vend->vendChannels()->exists() and $productMapping->productMappingItems()->exists()) {
                    $vend->vendChannels()->update(['product_id' => null]);
                    foreach($productMapping->productMappingItems as $productMappingItem) {
                        $vendChannel = $vend->vendChannels()->where('code', (int)$productMappingItem->channel_code)->first();
                        if($vendChannel) {
                            $vendChannel->product_id = $productMappingItem->product_id;
                            if($productMapping->selling_price_type) {
                                $sellingPrice = SellingPrice::where('product_id', $productMappingItem->product_id)->where('type', $productMapping->selling_price_type)->first();
                                if($sellingPrice) {
                                    $productMappingItem->update(['server_amount' => $sellingPrice->amount]);
                                }
                            }
                            $vendChannel->save();
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

        if($vendChannel and $productMappingItem) {
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

        if($productMapping) {
            // Find the associated ProductMappingItem using channel_code
            $productMappingItem = $productMapping->productMappingItems()
                ->where('channel_code', (int)$vendChannel->code)
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

}