<?php

namespace App\Jobs\Vend;

use App\Jobs\SaveVendChannelsJson;
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

        if(isset($input->processed) and isset($input->processed['channels'])) {
            $channels = $input->processed['channels'];
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
                    $this->syncVendChannelErrorLog($vend, $channel['channel_code'], $channel['error_code']);
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
            SaveVendChannelsJson::dispatch($vend->id);
        }
    }
}
