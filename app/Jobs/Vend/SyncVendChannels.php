<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelRecord;
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
        $vendChannelRecord = null;

        if(isset($input) and isset($input['channels'])) {
            $channels = $input['channels'];
            foreach($channels as $channel) {
                $prevVendChannel = VendChannel::where('vend_id', $vend->id)->where('code', $channel['channel_code'])->first();

                $data = [
                    'amount' => $channel['amount'],
                    'amount2' => isset($channel['amount2']) ? $channel['amount2'] : 0,
                    'capacity' => $channel['capacity'],
                    'discount_group' => isset($channel['discount_group']) ? $channel['discount_group'] : null,
                    'is_active' => $this->getVendChannelStatus($channel),
                    'locked_qty' => isset($channel['locked_qty']) ? $channel['locked_qty'] : 0,
                    'qty' => $channel['qty'],
                    'sku_code' => isset($channel['sku_code']) ? $channel['sku_code'] : null,
                ];

                // Check condition and add qty_sold_at only if the condition meets
                if ($prevVendChannel && $prevVendChannel->qty != 0 && $channel['qty'] == 0) {
                    $data['qty_sold_at'] = Carbon::now();
                    $data['qty_restocked_at'] = null;
                }

                if ($prevVendChannel && $prevVendChannel->qty == 0 && $channel['qty'] > 0) {
                    $data['qty_restocked_at'] = Carbon::now();
                    $data['qty_sold_at'] = null;
                }

                $vendChannel = VendChannel::updateOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $channel['channel_code'],
                ], $data);

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
                }else {
                    $vendChannel->update([
                        'qty_not_available_duration' => null,
                    ]);
                }

                if($vendChannel->is_active) {
                    $this->syncProductMappingItem($vendChannel, $channel);
                    SyncVendChannelErrorLog::dispatch($vend, $channel['channel_code'], $channel['error_code']);
                }
            }
            SaveVendChannelsJson::dispatch($vend->id, $this->input)->onQueue('default');
            $this->deliveryProductMappingService->syncVendChannels(null, $vend->id);
        }

        // handle VendChannelRecord
        if(isset($input) and isset($input['label'])) {

            $input['channels'] = array_values(array_filter($input['channels'], function($channel) {
                return $channel['capacity'] > 0;
            }));

            if($input['label'] == 'B') {
                $lastRecord = VendChannelRecord::where('vend_id', $vend->id)->orderBy('before_data_created_at', 'desc')->first();

                if($lastRecord && $lastRecord->after_data_created_at == null) {
                    $lastRecord->update([
                        'customer_id' => $vend->customer_id,
                        'operator_id' => $vend->operator_id,
                        'before_data_json' => $input,
                        'before_data_created_at' => Carbon::now(),
                        'before_label' => $input['label'],
                    ]);
                    $vendChannelRecord = $lastRecord;
                }else {
                    $vendChannelRecord = VendChannelRecord::create([
                        'customer_id' => $vend->customer_id,
                        'operator_id' => $vend->operator_id,
                        'vend_id' => $vend->id,
                        'before_data_json' => $input,
                        'before_data_created_at' => Carbon::now(),
                        'before_label' => $input['label'],
                    ]);
                }

                $this->syncVendChannelRecordVMCBeforeQty($vendChannelRecord);
            }

            if($input['label'] == 'A') {
                $vendChannelRecord = VendChannelRecord::query()
                    ->where('vend_id', $vend->id)
                    ->where('before_data_created_at', '>=', Carbon::now()->subHour())
                    ->whereNull('after_data_created_at')
                    ->orderBy('before_data_created_at', 'desc')
                    ->first();
                if($vendChannelRecord) {
                    $vendChannelRecord->update([
                        'after_data_json' => $input,
                        'after_data_created_at' => Carbon::now(),
                        'after_label' => $input['label'],
                    ]);

                    if($vendChannelRecord->stage_data_created_at) {
                        $vendChannelRecord->update([
                            'stage_data_json' => null,
                            'stage_data_created_at' => null,
                            'stage_label' => null,
                        ]);
                    }

                    $this->syncVendChannelRecordVMCAfterQty($vendChannelRecord);
                }
            }

            if($input['label'] == 'S') {
                $vendChannelRecord = VendChannelRecord::query()
                    ->where('vend_id', $vend->id)
                    ->where('before_data_created_at', '>=', Carbon::now()->subHour())
                    ->whereNull('after_data_created_at')
                    ->orderBy('before_data_created_at', 'desc')
                    ->first();
                if($vendChannelRecord && $vendChannelRecord->stage_data_created_at == null) {
                    $vendChannelRecord->update([
                        'stage_data_json' => $input,
                        'stage_data_created_at' => Carbon::now(),
                        'stage_label' => $input['label'],
                    ]);
                }

                $checkExpiredVendChannelRecord = VendChannelRecord::query()
                    ->where('vend_id', $vend->id)
                    ->where('before_data_created_at', '<', Carbon::now()->subHour())
                    ->whereNull('after_data_created_at')
                    ->whereNotNull('stage_data_created_at')
                    ->orderBy('before_data_created_at', 'desc')
                    ->first();

                if($checkExpiredVendChannelRecord) {
                    $this->convertStageVendChannelRecordToAfterVendChannelRecords($checkExpiredVendChannelRecord);
                }

            }
        }
    }

    private function convertStageVendChannelRecordToAfterVendChannelRecords(VendChannelRecord $vendChannelRecord)
    {
        $vendChannelRecord->update([
            'after_data_json' => $vendChannelRecord->stage_data_json,
            'after_data_created_at' => $vendChannelRecord->stage_data_created_at,
            'after_label' => $vendChannelRecord->stage_label,
            'stage_data_json' => null,
            'stage_data_created_at' => null,
            'stage_label' => null,
        ]);

        $this->syncVendChannelRecordVMCAfterQty($vendChannelRecord);
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

    private function getValidVendChanne($channel)
    {
        if($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
            return true;
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

    private function syncVendChannelRecordVMCBeforeQty(VendChannelRecord $vendChannelRecord)
    {
        if ($vendChannelRecord->opsJobItem && $vendChannelRecord->opsJobItem->opsJobItemChannels()->exists()) {
            $vendChannelRecord->opsJobItem->opsJobItemChannels->each(function($opsJobItemChannel) use ($vendChannelRecord) {
                $channels = $vendChannelRecord->before_data_json['channels'] ?? [];

                foreach ($channels as $channel) {
                    if (isset($channel['channel_code']) && $channel['channel_code'] == $opsJobItemChannel->vend_channel_code) {
                        $opsJobItemChannel->update([
                            'vmc_before_qty' => $channel['qty'], // Update with the 'qty' value from the matched channel
                        ]);
                        break; // Exit the loop once the matching channel is found
                    }
                }
            });
        }
    }


    private function syncVendChannelRecordVMCAfterQty(VendChannelRecord $vendChannelRecord)
    {
        if ($vendChannelRecord->opsJobItem && $vendChannelRecord->opsJobItem->opsJobItemChannels()->exists()) {
            $vendChannelRecord->opsJobItem->opsJobItemChannels->each(function($opsJobItemChannel) use ($vendChannelRecord) {
                $channels = $vendChannelRecord->after_data_json['channels'] ?? [];

                foreach ($channels as $channel) {
                    if (isset($channel['channel_code']) && $channel['channel_code'] == $opsJobItemChannel->vend_channel_code) {
                        $opsJobItemChannel->update([
                            'vmc_after_qty' => $channel['qty'], // Update with the 'qty' value from the matched channel
                        ]);
                        break; // Exit the loop once the matching channel is found
                    }
                }
            });
        }
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