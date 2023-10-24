<?php

namespace App\Services;

use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryProductMappingVendChannel;
use Carbon\Carbon;

class DeliveryProductMappingService
{
    // sync delivery platform channel item upon binded vend to delivery product mapping
    public function syncVendChannels($deliveryProductMappingId = null, $vendId = null)
    {
        // handle multiple source of update (from vm, template binding vend, template binding channel & item)
        $deliveryProductMappingVends = DeliveryProductMappingVend::query()
          ->when($deliveryProductMappingId, function($query, $search) {
              $query->where('delivery_product_mapping_id', $search);
          })
          ->when($vendId, function($query, $search) {
              $query->where('vend_id', $search);
          })
          ->get();

        if(count($deliveryProductMappingVends) > 0) {
          foreach($deliveryProductMappingVends as $deliveryProductMappingVend) {
            if($deliveryProductMappingVend and $deliveryProductMappingVend->vend->vendChannels()->exists()) {
              foreach($deliveryProductMappingVend->vend->vendChannels as $vendChannel) {
                if($deliveryProductMappingVend->deliveryProductMapping->deliveryProductMappingItems()->exists()) {
                  // create the existing vend channel data from android with local created template channel info
                  foreach($deliveryProductMappingVend->deliveryProductMapping->deliveryProductMappingItems as $item) {
                    if($item->channel_code === $vendChannel->code) {
                      $deliveryProductMappingVendChannel = DeliveryProductMappingVendChannel::query()
                        ->where('delivery_product_mapping_item_id', $item->id)
                        ->where('delivery_product_mapping_vend_id', $deliveryProductMappingVend->id)
                        ->where('vend_channel_id', $vendChannel->id)
                        ->first();

                      // update existing if found
                      if($deliveryProductMappingVendChannel) {
                        $deliveryProductMappingVendChannel->update([
                          'amount' => $item->amount,
                          'delivery_product_mapping_id' => $deliveryProductMappingId,
                          // if template and reserved logic checking is true, consider active for delivery platform channel item
                          'is_active' =>
                            $deliveryProductMappingVend->is_active and
                            $this->getVendChannelStatus(
                              $vendChannel->capacity,
                              $vendChannel->qty,
                              $item->deliveryProductMapping->reserved_percent,
                              $item->deliveryProductMapping->reserved_qty) ?
                            true : false,
                          'qty' => $item->qty,
                          'reserved_percent' => $deliveryProductMappingVendChannel->reserved_percent ? $deliveryProductMappingVendChannel->reserved_percent : $item->deliveryProductMapping->reserved_percent,
                          'reserved_qty' => $deliveryProductMappingVendChannel->reserved_qty ? $deliveryProductMappingVendChannel->reserved_qty : $item->deliveryProductMapping->reserved_qty,
                          'vend_channel_code' => $vendChannel->code,
                          'vend_code' => $vendChannel->vend->code,
                          'vend_id' => $vendChannel->vend->id,
                        ]);
                      } else {
                        // dd($deliveryProductMappingVend->toArray());
                        // create new if not found
                        $deliveryProductMappingVend->deliveryProductMappingVendChannels()->create([
                          'delivery_product_mapping_item_id' => $item->id,
                          'delivery_product_mapping_vend_id' => $deliveryProductMappingVend->id,
                          'vend_channel_id' => $vendChannel->id,
                          'amount' => $item->amount,
                          // if template and reserved logic checking is true, consider active for delivery platform channel item
                          'is_active' =>
                            $deliveryProductMappingVend->is_active and
                            $this->getVendChannelStatus(
                              $vendChannel->capacity,
                              $vendChannel->qty,
                              $item->deliveryProductMapping->reserved_percent,
                              $item->deliveryProductMapping->reserved_qty) ?
                            true : false,
                          'qty' => $item->qty,
                          'reserved_percent' => $item->deliveryProductMapping->reserved_percent,
                          'reserved_qty' => $item->deliveryProductMapping->reserved_qty,
                          'vend_channel_code' => $vendChannel->code,
                          'vend_code' => $vendChannel->vend->code,
                          'vend_id' => $vendChannel->vend->id,
                        ]);
                      }
                    }
                  }
                  // update delivery product mapping vend channels json (parent)
                  // $deliveryProductMappingVend->update([
                  //   'delivery_product_mapping_vend_channels_json' =>
                  //     $deliveryProductMappingVend
                  //     ->deliveryProductMappingVendChannels()
                  //     ->with('vendChannel')
                  //     ->get(),
                  // ]);
                }
              }
            }
          }
          return true;
        }
        return false;
    }

    // retrieve vend channel status after apply logic (reserved percent then reserved qty)
    private function getVendChannelStatus($vendChannelCapacity = 0, $vendChannelQty = 0, $reservedPercent = 0, $reservedQty = 0)
    {
        $status = false;
        if($vendChannelCapacity > 0) {
            $availableQtyPercent = $vendChannelQty/ $vendChannelCapacity * 100;
            if($availableQtyPercent >= $reservedPercent) {
              if(($vendChannelQty - $reservedQty) > 0) {
                $status = true;
              }
            }
        }
        return $status;
    }
}