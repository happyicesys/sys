<?php

namespace App\Services;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Jobs\SyncDeliveryProductMappingVendChannels;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryProductMapping;
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
          ->whereNull('end_date')
          ->get();

        if(count($deliveryProductMappingVends) > 0) {
          foreach($deliveryProductMappingVends as $deliveryProductMappingVend) {
            SyncDeliveryProductMappingVendChannels::dispatch($deliveryProductMappingVend)->onQueue('high');
            NotifyDeliveryPlatformUpdateMenu::dispatch($deliveryProductMappingVend)->onQueue('high');
          }
          return true;
        }
        return false;
    }

    // sync delivery product mapping vend channel order qty by adding when order created and subtract when order retrieved or expired
    public function syncDeliveryProductMappingVendChannelOrderQty(
      DeliveryProductMappingVendChannel $deliveryProductMappingVendChannel,
      $orderQty,
      $isAddition = true
    )
    {
      if($isAddition) {
        $deliveryProductMappingVendChannel->order_qty = ($deliveryProductMappingVendChannel->order_qty + $orderQty) > 0 ? ($deliveryProductMappingVendChannel->order_qty + $orderQty) : 0;
      }else {
        $deliveryProductMappingVendChannel->order_qty = ($deliveryProductMappingVendChannel->order_qty - $orderQty) > 0 ? ($deliveryProductMappingVendChannel->order_qty - $orderQty) : 0;
      }
      $historyOrderQty = $deliveryProductMappingVendChannel->order_qty_json ?? [];
      $historyOrderQty[] = [
        'previous_order_qty' => $deliveryProductMappingVendChannel->order_qty,
        'order_qty' => $orderQty,
        'is_addition' => $isAddition,
        'created_at' => Carbon::now()->toDateTimeString(),
      ];
      $deliveryProductMappingVendChannel->order_qty_json = $historyOrderQty;
      $deliveryProductMappingVendChannel->save();
    }

    public function getBundleSalesOptions(DeliveryProductMapping $deliveryProductMapping)
    {
      switch($deliveryProductMapping->deliveryPlatformOperator->deliveryPlatform->slug) {
        case 'grab':
          return Grab::CAMPAIGN_BUNDLE_MAPPING;
          break;
        default:
           return [];
      }
    }

    // retrieve vend channel status after apply logic (reserved percent and reserved qty, whichever higher)
    public function getDeliveryVendChannelStatus($vendChannel, $deliveryProductMappingVendChannel)
    {
        $vendChannelCapacity = $vendChannel->capacity;
        $vendChannelQty = $vendChannel->qty;
        $reservedPercent = $deliveryProductMappingVendChannel->reserved_percent;
        $reservedQty = $deliveryProductMappingVendChannel->reserved_qty;
        $orderQty = $deliveryProductMappingVendChannel->order_qty;

        $status = false;
        $calReservedQty = 0;
        $availableQty = 0;
        if($vendChannelCapacity > 0) {
          $reservedPercentQty = $vendChannelCapacity * $reservedPercent / 100;
          if($reservedPercentQty > $reservedQty) {
            $calReservedQty = $reservedPercentQty;
          }else {
            $calReservedQty = $reservedQty;
          }

          $availableQty = $vendChannelQty - $calReservedQty - $orderQty;

          if($availableQty > 0) {
            $status = true;
          }else {
            $availableQty = 0;
          }
        }
        return [
          'status' => $status,
          'available_qty' => $availableQty,
        ];
    }
}