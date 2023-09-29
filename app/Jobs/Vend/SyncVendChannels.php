<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\ProductMappingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVendChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                if($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
                    $vendChannel = VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'amount' => $channel['amount'],
                        'amount2' => isset($channel['amount2']) ? $channel['amount2'] : 0,
                        'capacity' => $channel['capacity'],
                        'discount_group' => isset($channel['discount_group']) ? $channel['discount_group'] : null,
                        'qty' => $channel['qty'],
                        'is_active' => true,
                        'locked_qty' => isset($channel['locked_qty']) ? $channel['locked_qty'] : 0,
                        'sku_code' => isset($channel['sku_code']) ? $channel['sku_code'] : null,
                    ]);
                    if(!$vendChannel->product_id) {
                        $vendChannel->update(['product_id' =>
                            $vend->productMapping()->exists() &&
                            $vend->productMapping->productMappingItems()->exists() &&
                            $vend->productMapping->productMappingItems()->where('channel_code', $channel['channel_code'])->first() ?
                            $vend->productMapping->productMappingItems()->where('channel_code', $channel['channel_code'])->first()->product_id :
                            null
                        ]);
                    }
                    SyncVendChannelErrorLog::dispatch($vend, $channel['channel_code'], $channel['error_code']);
                }else {
                    $vendChannelFalse = VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'amount' => $channel['amount'],
                        'amount2' => isset($channel['amount2']) ? $channel['amount2'] : 0,
                        'capacity' => $channel['capacity'],
                        'discount_group' => isset($channel['discount_group']) ? $channel['discount_group'] : null,
                        'qty' => $channel['qty'],
                        'is_active' => false,
                        'locked_qty' => isset($channel['locked_qty']) ? $channel['locked_qty'] : 0,
                        'sku_code' => isset($channel['sku_code']) ? $channel['sku_code'] : null,
                    ]);
                }
            }
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
        }
    }

    // private function updateProductIdByVendChannel($vendChannel)
    // {
    //     if(
    //         $vendChannel->vend->productMapping()->exists() and
    //         $vendChannel->vend->productMapping->productMappingItems()->exists()
    //     ) {
    //         $productMappingItem = ProductMappingItem::query()
    //                             ->where('product_mapping_id', $vendChannel->vend->productMapping->id)
    //                             ->where('channel_code', $vendChannel->code)
    //                             ->first();
    //         if($productMappingItem) {
    //             $vendChannel->update(['product_id' => $productMappingItem->product_id]);
    //         }
    //     }

    // }
}