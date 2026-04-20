<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelRecord;
use App\Models\VendChannelStockEvent;
use App\Models\VendTransaction;
use App\Models\ProductMappingItem;
use App\Services\DeliveryProductMappingService;
use App\Services\ProductMappingService;
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
    public function handle(DeliveryProductMappingService $deliveryProductMappingService, ProductMappingService $productMappingService)
    {
        $vend = $this->vend;
        $input = $this->input;

        if (isset($input) and isset($input['channels'])) {
            $channels = $input['channels'];
            $prevVendChannels = VendChannel::where('vend_id', $vend->id)->get()->keyBy('code');
            $errorRates = $this->getChannelErrorRatesArray($vend->id);

            foreach ($channels as $channel) {
                $prevVendChannel = $prevVendChannels->get($channel['channel_code']);

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

                $stockEvent = null;

                // Check condition and add qty_sold_at only if the condition meets
                if ($prevVendChannel && $prevVendChannel->qty != 0 && $channel['qty'] == 0) {
                    $occurredAt = Carbon::now();
                    $data['qty_sold_at'] = $occurredAt;
                    $data['qty_restocked_at'] = null;
                    $stockEvent = [
                        'event_type' => VendChannelStockEvent::TYPE_SOLD_OUT,
                        'qty_before' => $prevVendChannel->qty,
                        'qty_after' => $channel['qty'],
                        'occurred_at' => $occurredAt,
                        'product_id' => $prevVendChannel->product_id,
                    ];
                }

                if ($prevVendChannel && $prevVendChannel->qty == 0 && $channel['qty'] > 0) {
                    $occurredAt = Carbon::now();
                    $data['qty_restocked_at'] = $occurredAt;
                    $data['qty_sold_at'] = null;
                    $stockEvent = [
                        'event_type' => VendChannelStockEvent::TYPE_RESTOCKED,
                        'qty_before' => $prevVendChannel->qty,
                        'qty_after' => $channel['qty'],
                        'occurred_at' => $occurredAt,
                        'product_id' => $prevVendChannel->product_id,
                    ];
                }

                // Initial updateOrCreate
                $vendChannel = VendChannel::updateOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $channel['channel_code'],
                ], $data);

                // Combine remaining updates into one if possible
                $updates = [];
                if ($vendChannel->is_active) {
                    $updates['error_rate_json'] = $this->calculateChannelErrorRateJson($vendChannel->id, $errorRates);
                }

                if ($vendChannel->qty_sold_at and $vendChannel->qty_restocked_at) {
                    $updates['qty_not_available_duration'] = $vendChannel->qty_sold_at->diffForHumans($vendChannel->qty_restocked_at, true);
                } else {
                    $updates['qty_not_available_duration'] = null;
                }

                if (!empty($updates)) {
                    $vendChannel->update($updates);
                }

                if ($stockEvent) {
                    $this->recordStockEvent($vendChannel, $stockEvent);
                }

                if ($vendChannel->is_active) {
                    SyncVendChannelErrorLog::dispatch($vend, $channel['channel_code'], $channel['error_code']);
                }
            }
            $productMappingService->syncChannelsByVend($vend);
            SaveVendChannelsJson::dispatch($vend->id, $this->input)->onQueue('default');
            $deliveryProductMappingService->syncVendChannels(null, $vend->id);
        }

        // handle VendChannelRecord
        if (isset($input) and isset($input['label'])) {

            $input['channels'] = array_values(array_filter($input['channels'], function ($channel) {
                return $channel['capacity'] > 0;
            }));

            if ($input['label'] == 'B') {
                $lastRecord = VendChannelRecord::where('vend_id', $vend->id)->orderBy('before_data_created_at', 'desc')->first();

                if ($lastRecord && $lastRecord->after_data_created_at == null) {
                    $lastRecord->update([
                        'customer_id' => $vend->customer_id,
                        'operator_id' => $vend->operator_id,
                        'before_data_json' => $input,
                        'before_data_created_at' => Carbon::now(),
                        'before_label' => $input['label'],
                    ]);
                    $vendChannelRecord = $lastRecord;
                } else {
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

            if ($input['label'] == 'A') {
                $vendChannelRecord = VendChannelRecord::query()
                    ->where('vend_id', $vend->id)
                    ->where('before_data_created_at', '>=', Carbon::now()->subHour())
                    ->whereNull('after_data_created_at')
                    ->orderBy('before_data_created_at', 'desc')
                    ->first();
                if ($vendChannelRecord) {
                    $vendChannelRecord->update([
                        'after_data_json' => $input,
                        'after_data_created_at' => Carbon::now(),
                        'after_label' => $input['label'],
                    ]);

                    if ($vendChannelRecord->stage_data_created_at) {
                        $vendChannelRecord->update([
                            'stage_data_json' => null,
                            'stage_data_created_at' => null,
                            'stage_label' => null,
                        ]);
                    }

                    $this->syncVendChannelRecordVMCAfterQty($vendChannelRecord);
                }
            }

            if ($input['label'] == 'S') {
                $vendChannelRecord = VendChannelRecord::query()
                    ->where('vend_id', $vend->id)
                    ->where('before_data_created_at', '>=', Carbon::now()->subHour())
                    ->whereNull('after_data_created_at')
                    ->orderBy('before_data_created_at', 'desc')
                    ->first();
                if ($vendChannelRecord && $vendChannelRecord->stage_data_created_at == null) {
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

                if ($checkExpiredVendChannelRecord) {
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
        if ($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
            return true;
        } else {
            return false;
        }
    }

    private function getValidVendChannel($channel)
    {
        if ($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
            return true;
        }
    }



    private function syncVendChannelRecordVMCBeforeQty(VendChannelRecord $vendChannelRecord)
    {
        if ($vendChannelRecord->opsJobItem && $vendChannelRecord->opsJobItem->opsJobItemChannels()->exists()) {
            $vendChannelRecord->opsJobItem->opsJobItemChannels->each(function ($opsJobItemChannel) use ($vendChannelRecord) {
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
            $vendChannelRecord->opsJobItem->opsJobItemChannels->each(function ($opsJobItemChannel) use ($vendChannelRecord) {
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

    private function getChannelErrorRatesArray($vendId)
    {
        $sixDaysAgo = Carbon::today()->subDays(6)->startOfDay()->toDateTimeString();
        $twoDaysAgo = Carbon::today()->subDays(2)->startOfDay()->toDateTimeString();
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();

        $singleData = \App\Models\VendTransaction::query()
            ->where('vend_id', $vendId)
            ->whereNotNull('vend_channel_id')
            ->where('is_multiple', false)
            ->where('transaction_datetime', '>=', $sixDaysAgo)
            ->selectRaw('
                vend_channel_id,
                COUNT(id) as seven_days_total_count,
                COUNT(CASE WHEN vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1) THEN 1 END) as seven_days_error_count,
                COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as three_days_total_count,
                COUNT(CASE WHEN transaction_datetime >= ? AND vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1) THEN 1 END) as three_days_error_count,
                COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as one_day_total_count,
                COUNT(CASE WHEN transaction_datetime >= ? AND vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1) THEN 1 END) as one_day_error_count
            ', [$twoDaysAgo, $twoDaysAgo, $todayStart, $todayStart])
            ->groupBy('vend_channel_id')
            ->get()
            ->keyBy('vend_channel_id');

        $multiData = \App\Models\VendTransactionItem::query()
            ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
            ->where('vend_transactions.vend_id', $vendId)
            ->whereNotNull('vend_transaction_items.vend_channel_id')
            ->where('vend_transactions.is_multiple', true)
            ->where('vend_transactions.transaction_datetime', '>=', $sixDaysAgo)
            ->selectRaw('
                vend_transaction_items.vend_channel_id,
                COUNT(vend_transaction_items.id) as seven_days_total_count,
                COUNT(CASE WHEN vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as seven_days_error_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as three_days_total_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as three_days_error_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as one_day_total_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as one_day_error_count
            ', [$twoDaysAgo, $twoDaysAgo, $todayStart, $todayStart])
            ->groupBy('vend_transaction_items.vend_channel_id')
            ->get()
            ->keyBy('vend_channel_id');

        return [
            'single' => $singleData,
            'multi' => $multiData,
        ];
    }

    private function calculateChannelErrorRateJson($vendChannelID, $errorRates)
    {
        $singleData = isset($errorRates['single']) && isset($errorRates['single'][$vendChannelID]) ? $errorRates['single'][$vendChannelID] : null;
        $multiData = isset($errorRates['multi']) && isset($errorRates['multi'][$vendChannelID]) ? $errorRates['multi'][$vendChannelID] : null;

        $sevenDaysTotal = ($singleData->seven_days_total_count ?? 0) + ($multiData->seven_days_total_count ?? 0);
        $sevenDaysError = ($singleData->seven_days_error_count ?? 0) + ($multiData->seven_days_error_count ?? 0);
        $threeDaysTotal = ($singleData->three_days_total_count ?? 0) + ($multiData->three_days_total_count ?? 0);
        $threeDaysError = ($singleData->three_days_error_count ?? 0) + ($multiData->three_days_error_count ?? 0);
        $oneDayTotal = ($singleData->one_day_total_count ?? 0) + ($multiData->one_day_total_count ?? 0);
        $oneDayError = ($singleData->one_day_error_count ?? 0) + ($multiData->one_day_error_count ?? 0);

        return [
            'seven_days_total_count' => $sevenDaysTotal,
            'seven_days_error_count' => $sevenDaysError,
            'seven_days_error_rate' => $sevenDaysTotal > 0 ? round(($sevenDaysError / $sevenDaysTotal) * 100, 2) : 0,
            'three_days_total_count' => $threeDaysTotal,
            'three_days_error_count' => $threeDaysError,
            'three_days_error_rate' => $threeDaysTotal > 0 ? round(($threeDaysError / $threeDaysTotal) * 100, 2) : 0,
            'one_day_total_count' => $oneDayTotal,
            'one_day_error_count' => $oneDayError,
            'one_day_error_rate' => $oneDayTotal > 0 ? round(($oneDayError / $oneDayTotal) * 100, 2) : 0,
        ];
    }

    private function recordStockEvent(VendChannel $vendChannel, array $event): void
    {
        VendChannelStockEvent::create([
            'vend_channel_id' => $vendChannel->id,
            'vend_id' => $vendChannel->vend_id,
            'product_id' => $event['product_id'] ?? $vendChannel->product_id,
            'event_type' => $event['event_type'],
            'qty_before' => $event['qty_before'],
            'qty_after' => $event['qty_after'],
            'occurred_at' => $event['occurred_at'],
        ]);
    }
}
