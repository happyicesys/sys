<?php

namespace App\Services;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\ProductMapping;
use App\Models\Vend;
use Carbon\Carbon;

class ProductMappingService
{
    public function syncChannels(ProductMapping $productMapping)
    {
        if($productMapping->vends()->exists()) {
            foreach($productMapping->vends as $vend) {
                if($vend->vendChannels()->exists() and $productMapping->productMappingItems()->exists()) {
                    $vendData = Vend::findOrFail($vend->id);
                    $vendData->vendChannels()->update(['product_id' => null]);

                    $vendChannels = $vend->vendChannels;
                    $productMappingItems = $productMapping->productMappingItems;
                    foreach($productMappingItems as $productMappingItem) {
                        $vendChannel = $vendChannels->where('code', $productMappingItem->channel_code)->first();
                        if($vendChannel) {
                            $vendChannel->product_id = $productMappingItem->product_id;
                            $vendChannel->save();
                        }
                    }
                    SaveVendChannelsJson::dispatch($vend->id)->onQueue('high');
                }
            }
        }
    }
}