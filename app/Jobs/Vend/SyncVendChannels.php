<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Vend;
use App\Models\VendChannel;
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
            $vend->vendChannels()->update(['is_active' => false]);
            foreach($channels as $channel) {
                if($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
                    $vendChannel = VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'qty' => $channel['qty'],
                        'capacity' => $channel['capacity'],
                        'amount' => $channel['amount'],
                        'is_active' => true,
                    ]);
                    SyncVendChannelErrorLog::dispatch($vend, $channel['channel_code'], $channel['error_code']);
                }else {
                    $vendChannelFalse = VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'qty' => $channel['qty'],
                        'capacity' => $channel['capacity'],
                        'amount' => $channel['amount'],
                        'is_active' => false,
                    ]);
                }
            }
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
        }
    }

    private function getProductIdByChannelCode($channelCode)
    {
        $vend = $this->vend;
        $productMapping = $vend->productMapping;

        if(
            $channelCode and
            $vend->vendChannels()->exists()  and
            $productMapping and
            $productMapping->productMappingItems()->exists()
        ) {
            $productMappingItem = $productMapping->productMappingItems()->where('channel_code', $channelCode)->first();
            if($productMappingItem) {
                return $productMappingItem->product_id;
            }
        }

    }
}
