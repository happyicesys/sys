<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\ProductMappingItem;
use App\Services\DeliveryProductMappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVendChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryProductMappingService;
    protected $input;
    protected $vend;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
        $this->deliveryProductMappingService = new DeliveryProductMappingService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vend = $this->vend;
        $input = $this->input;

        if(isset($input) and isset($input['channels'])) {
            $channels = $input['channels'];
            foreach($channels as $channel) {
                $vendChannel = VendChannel::updateOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $channel['channel_code'],
                ], [
                    'amount' => $channel['amount'],
                    'amount2' => isset($channel['amount2']) ? $channel['amount2'] : 0,
                    'capacity' => $channel['capacity'],
                    'discount_group' => isset($channel['discount_group']) ? $channel['discount_group'] : null,
                    'qty' => $channel['qty'],
                    'is_active' => $this->getVendChannelStatus($channel),
                    'locked_qty' => isset($channel['locked_qty']) ? $channel['locked_qty'] : 0,
                    'sku_code' => isset($channel['sku_code']) ? $channel['sku_code'] : null,
                ]);

                if($vendChannel->is_active) {
                    $this->syncProductMappingItem($vendChannel, $channel);
                    $this->deliveryProductMappingService->syncVendChannels(null, $vend->id);
                    SyncVendChannelErrorLog::dispatch($vend, $channel['channel_code'], $channel['error_code']);
                }
            }
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
        }
    }

    // get vend channel status by custom logic
    private function getVendChannelStatus($channel)
    {
        if($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
            return true;
        }else {
            return false;
        }
    }

    // sync with product mapping template item
    private function syncProductMappingItem(VendChannel $vendChannel, $input)
    {
        $vendChannel->update(['product_id' =>
            $vendChannel->vend->productMapping()->exists() &&
            $vendChannel->vend->productMapping->productMappingItems()->exists() &&
            $vendChannel->vend->productMapping->productMappingItems()->where('channel_code', $input['channel_code'])->first() ?
            $vendChannel->vend->productMapping->productMappingItems()->where('channel_code', $input['channel_code'])->first()->product_id :
            null
        ]);
    }
}