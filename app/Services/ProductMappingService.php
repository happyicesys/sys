<?php

namespace App\Services;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
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
}