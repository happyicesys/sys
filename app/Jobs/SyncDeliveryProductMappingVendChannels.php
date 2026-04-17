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

class SyncDeliveryProductMappingVendChannels implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Deduplicate rapid dispatches for the same mapping vend within 60 seconds
    public $uniqueFor = 60;

    public function uniqueId(): string
    {
        return 'delivery_mapping_vend_' . $this->deliveryProductMappingVend->id;
    }

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
        // Eager-load all needed relations upfront to avoid N+1 lazy loads inside the nested loops
        $this->deliveryProductMappingVend->load([
            'vend.vendChannels',
            'deliveryProductMapping.deliveryProductMappingItems',
        ]);

        // Cache references to avoid repeated property traversal
        $vend = $this->deliveryProductMappingVend->vend;
        $deliveryProductMapping = $this->deliveryProductMappingVend->deliveryProductMapping;

        if($vend->vendChannels->isNotEmpty()) {
            foreach($vend->vendChannels as $vendChannel) {
              if($deliveryProductMapping->deliveryProductMappingItems->isNotEmpty()) {
                // create the existing vend channel data from android with local created template channel info
                foreach($deliveryProductMapping->deliveryProductMappingItems as $item) {
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
                        'delivery_product_mapping_id' => $deliveryProductMapping->id,
                        // if template and reserved logic checking is true, consider active for delivery platform channel item
                        'is_active' =>
                          $this->deliveryProductMappingVend->is_active and
                          $item->is_active and
                          $this->deliveryProductMappingService->getDeliveryVendChannelStatus(
                            $vendChannel,
                            $deliveryProductMappingVendChannel)['status'] ?
                          true : false,
                        'qty' => $item->qty,
                        'reserved_percent' => $deliveryProductMappingVendChannel->reserved_percent ? $deliveryProductMappingVendChannel->reserved_percent : $deliveryProductMapping->reserved_percent,
                        'reserved_qty' => $deliveryProductMappingVendChannel->reserved_qty ? $deliveryProductMappingVendChannel->reserved_qty : $deliveryProductMapping->reserved_qty,
                        'vend_channel_code' => $vendChannel->code,
                        'vend_code' => $vend->code,
                        'vend_id' => $vend->id,
                      ]);
                    } else {
                      // create new if not found
                      $this->deliveryProductMappingVend->deliveryProductMappingVendChannels()->create([
                        'delivery_product_mapping_item_id' => $item->id,
                        'delivery_product_mapping_vend_id' => $this->deliveryProductMappingVend->id,
                        'vend_channel_id' => $vendChannel->id,
                        'amount' => $item->amount,
                        'delivery_product_mapping_id' => $deliveryProductMapping->id,
                        // if template and reserved logic checking is true, consider active for delivery platform channel item
                        'is_active' =>
                          $this->deliveryProductMappingVend->is_active and
                          $item->is_active and
                          $this->deliveryProductMappingService->getDeliveryVendChannelStatus(
                            $vendChannel,
                            $this->deliveryProductMappingVend)['status'] ?
                          true : false,
                        'qty' => $item->qty,
                        'reserved_percent' => $deliveryProductMapping->reserved_percent,
                        'reserved_qty' => $deliveryProductMapping->reserved_qty,
                        'vend_channel_code' => $vendChannel->code,
                        'vend_code' => $vend->code,
                        'vend_id' => $vend->id,
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
