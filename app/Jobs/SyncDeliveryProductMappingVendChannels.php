<?php

namespace App\Jobs;

use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryProductMappingVendChannel;
use App\Services\DeliveryProductMappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncDeliveryProductMappingVendChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryProductMappingVend;
    protected $deliveryProductMappingService;
    /**
     * Create a new job instance.
     */
    public function __construct(DeliveryProductMappingVend $deliveryProductMappingVend)
    {
        $this->deliveryProductMappingVend = $deliveryProductMappingVend;
        $this->deliveryProductMappingService = new DeliveryProductMappingService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->deliveryProductMappingVend->vend->vendChannels()->exists()) {
            foreach($this->deliveryProductMappingVend->vend->vendChannels as $vendChannel) {
              if($this->deliveryProductMappingVend->deliveryProductMapping->deliveryProductMappingItems()->exists()) {
                // create the existing vend channel data from android with local created template channel info
                foreach($this->deliveryProductMappingVend->deliveryProductMapping->deliveryProductMappingItems as $item) {
                  if($item->channel_code === $vendChannel->code) {
                    $deliveryProductMappingVendChannel = DeliveryProductMappingVendChannel::query()
                      ->where('delivery_product_mapping_item_id', $item->id)
                      ->where('delivery_product_mapping_vend_id', $this->deliveryProductMappingVend->id)
                      ->where('vend_channel_id', $vendChannel->id)
                      ->first();

                    // update existing if found
                    if($deliveryProductMappingVendChannel) {
                      $deliveryProductMappingVendChannel->update([
                        'amount' => $item->amount,
                        'delivery_product_mapping_id' => $this->deliveryProductMappingVend->deliveryProductMapping->id,
                        // if template and reserved logic checking is true, consider active for delivery platform channel item
                        'is_active' =>
                          $this->deliveryProductMappingVend->is_active and
                          $item->is_active and
                          $this->deliveryProductMappingService->getDeliveryVendChannelStatus(
                            $vendChannel,
                            $deliveryProductMappingVendChannel)['status'] ?
                          true : false,
                        'qty' => $item->qty,
                        'reserved_percent' => $deliveryProductMappingVendChannel->reserved_percent ? $deliveryProductMappingVendChannel->reserved_percent : $item->deliveryProductMapping->reserved_percent,
                        'reserved_qty' => $deliveryProductMappingVendChannel->reserved_qty ? $deliveryProductMappingVendChannel->reserved_qty : $item->deliveryProductMapping->reserved_qty,
                        'vend_channel_code' => $vendChannel->code,
                        'vend_code' => $vendChannel->vend->code,
                        'vend_id' => $vendChannel->vend->id,
                      ]);
                    } else {
                      // create new if not found
                      $this->deliveryProductMappingVend->deliveryProductMappingVendChannels()->create([
                        'delivery_product_mapping_item_id' => $item->id,
                        'delivery_product_mapping_vend_id' => $this->deliveryProductMappingVend->id,
                        'vend_channel_id' => $vendChannel->id,
                        'amount' => $item->amount,
                        'delivery_product_mapping_id' => $this->deliveryProductMappingVend->deliveryProductMapping->id,
                        // if template and reserved logic checking is true, consider active for delivery platform channel item
                        'is_active' =>
                          $this->deliveryProductMappingVend->is_active and
                          $item->is_active and
                          $this->deliveryProductMappingService->getDeliveryVendChannelStatus(
                            $vendChannel,
                            $this->deliveryProductMappingVend)['status'] ?
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
              }else {
                // set the product mapping vend as inactive when no delivery product mapping item found
                $this->deliveryProductMappingVend->update([
                  'is_active' => false,
                ]);
              }
            }
          }else {
            $this->deliveryProductMappingVend->update([
              'is_active' => false,
            ]);
          }
    }
}
