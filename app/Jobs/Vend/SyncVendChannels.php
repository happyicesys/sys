<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Models\ProductMappingItem;
use App\Services\DeliveryProductMappingService;
use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
                $prevVendChannel = VendChannel::where('vend_id', $vend->id)->where('code', $channel['channel_code'])->first();

                $vendChannel = VendChannel::updateOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $channel['channel_code'],
                ], [
                    'amount' => $channel['amount'],
                    'amount2' => isset($channel['amount2']) ? $channel['amount2'] : 0,
                    'capacity' => $channel['capacity'],
                    'discount_group' => isset($channel['discount_group']) ? $channel['discount_group'] : null,
                    'is_active' => $this->getVendChannelStatus($channel),
                    'locked_qty' => isset($channel['locked_qty']) ? $channel['locked_qty'] : 0,
                    'qty' => $channel['qty'],
                    'qty_sold_at' => $prevVendChannel && $prevVendChannel->qty != 0 && $channel['qty'] == 0 ? Carbon::now() : ($prevVendChannel && $prevVendChannel->qty_sold_at ? $prevVendChannel->qty_sold_at : null),
                    'qty_restocked_at' => $prevVendChannel && $prevVendChannel->qty == 0 && $channel['qty'] > 0 ? Carbon::now() : ($prevVendChannel && $prevVendChannel->qty_restocked_at ? $prevVendChannel->qty_restocked_at : null),
                    'sku_code' => isset($channel['sku_code']) ? $channel['sku_code'] : null,
                ]);

                // update error rate json based on vend channel
                if($vendChannel->is_active) {
                    $vendChannel->update([
                        'error_rate_json' => $this->getChannelErrorRates($vendChannel->id)
                    ]);
                }

                if($vendChannel->qty_sold_at and $vendChannel->qty_restocked_at) {
                    $vendChannel->update([
                        'qty_not_available_duration' => $vendChannel->qty_sold_at->diffForHumans($vendChannel->qty_restocked_at, true),
                    ]);
                }

                if($vendChannel->is_active) {
                    $this->syncProductMappingItem($vendChannel, $channel);
                    SyncVendChannelErrorLog::dispatch($vend, $channel['channel_code'], $channel['error_code']);
                }
            }
            SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
            $this->deliveryProductMappingService->syncVendChannels(null, $vend->id);
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

    private function getChannelErrorRates($vendChannelID)
    {
        $vendTransaction = VendTransaction::query()
            ->where('vend_channel_id', $vendChannelID)
            ->where('transaction_datetime', '>=', Carbon::today()->subDays(6)->startOfDay()->toDateTimeString())
            ->groupBy('vend_channel_id')
            ->select(
                'id',
                'vend_channel_id',
                DB::raw(
                    'COUNT(id) as seven_days_total_count'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN error_code_normalized IS NULL THEN NULL
                            WHEN error_code_normalized = 0 THEN NULL
                            ELSE 1
                        END
                    ) as seven_days_error_count'
                )
            )
            ->selectRaw('COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as three_days_total_count', [Carbon::today()->subDays(2)->startOfDay()->toDateTimeString()])
            ->selectRaw('COUNT(CASE WHEN transaction_datetime >= ? AND error_code_normalized IS NOT NULL AND error_code_normalized != 0 THEN 1 END) as three_days_error_count', [Carbon::today()->subDays(2)->startOfDay()->toDateTimeString()])
            ->first();

        return [
            'seven_days_total_count' => isset($vendTransaction->seven_days_total_count) ? $vendTransaction->seven_days_total_count : 0,
            'seven_days_error_count' => isset($vendTransaction->seven_days_error_count) ? $vendTransaction->seven_days_error_count : 0,
            'seven_days_error_rate' => isset($vendTransaction->seven_days_total_count) && $vendTransaction->seven_days_total_count > 0 ? round(((isset($vendTransaction->seven_days_error_count) ? $vendTransaction->seven_days_error_count : 0) / $vendTransaction->seven_days_total_count) * 100, 2) : 0,
            'three_days_total_count' => isset($vendTransaction->three_days_total_count) ? $vendTransaction->three_days_total_count : 0,
            'three_days_error_count' => isset($vendTransaction->three_days_error_count) ? $vendTransaction->three_days_error_count : 0,
            'three_days_error_rate' => isset($vendTransaction->three_days_total_count) && $vendTransaction->three_days_total_count > 0 ? round(((isset($vendTransaction->three_days_error_count) && $vendTransaction->three_days_error_count) / $vendTransaction->three_days_total_count) * 100, 2) : 0,
        ];
    }
}