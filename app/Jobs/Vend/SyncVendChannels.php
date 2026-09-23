<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelRecord;
use App\Models\VendChannelStockEvent;
use App\Services\DeliveryProductMappingService;
use App\Services\ProductMappingService;
use App\Support\DispenseVerdict;
use App\Support\OpsJobFrameQty;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
            // A SKU-stocked machine (freezer, chiller — Vend::isSkuStocked) identifies a
            // row by PRODUCT; the code + suffix is a position label the frame relabels.
            // A vending machine identifies a row by the board's slot code.
            $skuStocked = $vend->isSkuStocked();
            $allRows = VendChannel::where('vend_id', $vend->id)->get();
            $prevVendChannels = $skuStocked
                ? $allRows->whereNotNull('product_id')->keyBy(fn ($row) => (int) $row->product_id)
                : $allRows->keyBy('code');
            $errorRates = $this->getChannelErrorRatesArray($vend->id);

            // SKU-stocked: the label release + relabel loop + retire must land as one
            // unit, or a failure mid-way leaves rows parked on negative codes — and a
            // queue worker reuses its connection, so an open transaction must never
            // leak into the next job.
            if ($skuStocked) {
                DB::beginTransaction();
            }
            try {
                if ($skuStocked) {
                    $this->releaseClaimedLabels($allRows, $channels);
                }
                foreach ($channels as $channel) {
                    // Normalize once: boards have sent non-canonical code strings
                    // ("017", padded) that miss the int-keyed lookup while MySQL
                    // coerces them to the same int on insert — which is how the
                    // vend 4753 duplicate-channel-17 row was born (2026-08-03).
                    $channelCode = (int) $channel['channel_code'];
                    $productId = isset($channel['product_id']) ? (int) $channel['product_id'] : null;
                    if ($skuStocked && ! $productId) {
                        continue; // no SKU, no row — an entry without identity cannot be stored
                    }
                    $prevVendChannel = $skuStocked ? $prevVendChannels->get($productId) : $prevVendChannels->get($channelCode);

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
                    if ($skuStocked) {
                        // Identity + position label. The label is relabelled on every frame so a
                        // SKU that moved between mappings keeps its row (and qty) and only its
                        // code changes; releaseClaimedLabels() has already freed the target.
                        $data['product_id'] = $productId;
                        $data['code'] = $channelCode;
                        $data['suffix'] = isset($channel['suffix']) && $channel['suffix'] !== '' ? strtoupper((string) $channel['suffix']) : null;
                    }

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

                    // Fold error-rate + availability duration into the single write
                    // below (was updateOrCreate followed by a second update()).
                    // For an existing channel the id is already known from the
                    // preloaded row; a brand-new channel has no history so its
                    // error-rate resolves to zeros regardless of id.
                    if ($data['is_active']) {
                        $data['error_rate_json'] = $this->calculateChannelErrorRateJson($prevVendChannel->id ?? 0, $errorRates);
                    }

                    $soldAt = array_key_exists('qty_sold_at', $data) ? $data['qty_sold_at'] : ($prevVendChannel->qty_sold_at ?? null);
                    $restockedAt = array_key_exists('qty_restocked_at', $data) ? $data['qty_restocked_at'] : ($prevVendChannel->qty_restocked_at ?? null);
                    if ($soldAt && $restockedAt) {
                        $data['qty_not_available_duration'] = Carbon::parse($soldAt)->diffForHumans(Carbon::parse($restockedAt), true);
                    } else {
                        $data['qty_not_available_duration'] = null;
                    }

                    // Single write per channel; reuse the preloaded row instead of
                    // updateOrCreate's extra SELECT round-trip. The miss path does
                    // pay for that SELECT, via updateOrCreate: vend_channels
                    // carries a unique (vend_id, code) index, and updateOrCreate
                    // goes through firstOrCreate → createOrFirst, which catches the
                    // unique violation and re-reads the winner. So losing a race
                    // against a concurrent report degrades into an update of the
                    // winner's row, never a duplicate and never an exception.
                    if ($prevVendChannel) {
                        $prevVendChannel->update($data);
                        $vendChannel = $prevVendChannel;
                    } elseif ($skuStocked) {
                        $vendChannel = VendChannel::create(['vend_id' => $vend->id] + $data);
                    } else {
                        $vendChannel = VendChannel::updateOrCreate([
                            'vend_id' => $vend->id,
                            'code' => $channelCode,
                        ], $data);
                    }

                    if ($stockEvent) {
                        $this->recordStockEvent($vendChannel, $stockEvent);
                    }

                    // A SKU-stocked machine has no motor faults (its frames always say 0), and
                    // the error-log job keys on the bare code, which a suffix makes ambiguous.
                    if ($data['is_active'] && ! $skuStocked) {
                        SyncVendChannelErrorLog::dispatch($vend, $channelCode, $channel['error_code']);
                    }
                }
                if ($skuStocked) {
                    $this->retireSkuRowsMissingFrom($channels, $allRows);
                    DB::commit();
                }
            } catch (\Throwable $e) {
                if ($skuStocked) {
                    DB::rollBack();
                }
                throw $e;
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

    /**
     * A vending board reports every slot on every frame, so a channel absent from
     * one is simply unchanged. A SKU-stocked machine's frame is built from its
     * whole planogram (ChannelFrameAdapter / FreezerChannelSync never send an
     * empty one), so a SKU missing from it has left the mapping. Left active, it
     * kept its old capacity and lost its product to the mapping sync — prod
     * 2026-09-19, C5001: 60 "Unmapped SKU" ghosts on the overview, the Ops
     * Dashboard and ops jobs. Deactivate them instead (the qty stays on the row,
     * so a SKU that comes back is reactivated with its ledger by the loop above).
     * Rows with no product at all on such a machine are legacy and retire too.
     *
     * @param  \Illuminate\Support\Collection<int,VendChannel>  $allRows
     */
    private function retireSkuRowsMissingFrom(array $channels, $allRows): void
    {
        if ($channels === []) {
            return;
        }
        $reported = [];
        foreach ($channels as $c) {
            if (! empty($c['product_id'])) {
                $reported[(int) $c['product_id']] = true;
            }
        }
        $gone = $allRows->filter(fn (VendChannel $c) => $c->is_active && ! isset($reported[(int) $c->product_id]));
        if ($gone->isEmpty()) {
            return;
        }
        VendChannel::whereIn('id', $gone->pluck('id'))->update(['is_active' => false]);
        Log::info('SKU rows retired — no longer in the planogram', ['vend_id' => $this->vend->id, 'labels' => $gone->map(fn ($c) => $c->label)->values()->all()]);
    }

    /**
     * Free every position label the frame gives to a DIFFERENT product than the row
     * holding it now, so the relabel in the loop never trips the unique
     * (vend_id, code, suffix_key) index — two SKUs swapping codes, a retired SKU's
     * old label handed to a new one, a legacy row with no product squatting on a
     * code. A released row gets a negative, id-unique code (no position) until the
     * loop or a later frame gives it a real one. Must run inside the transaction
     * the loop commits.
     *
     * @param  \Illuminate\Support\Collection<int,VendChannel>  $allRows
     */
    private function releaseClaimedLabels($allRows, array $channels): void
    {
        $claims = [];
        foreach ($channels as $c) {
            if (empty($c['product_id'])) {
                continue;
            }
            $suffix = isset($c['suffix']) && $c['suffix'] !== '' ? strtoupper((string) $c['suffix']) : null;
            $claims[\App\Support\ChannelCode::label((int) $c['channel_code'], $suffix)] = (int) $c['product_id'];
        }
        foreach ($allRows as $row) {
            $owner = $claims[$row->label] ?? null;
            if ($owner !== null && $owner !== (int) $row->product_id) {
                $row->forceFill(['code' => -$row->id, 'suffix' => null])->saveQuietly();
            }
        }
    }

    private function getVendChannelStatus($channel)
    {
        // A vending machine's board reports capacity, so capacity 0 there means "no such slot". A
        // Smart Freezer's capacity comes from the SKU's measured par (products.freezer_slot_qty),
        // which is blank until someone counts it — the slot still exists and still sells, so it
        // stays active and shows its par as "-" (2026-09-16).
        // A Smart Chiller is the same story since 2026-09-21: capacity is the SKU's
        // chiller_slot_qty, blank until someone counts it, and the channel still sells.
        if ($this->vend->isSkuStocked()) {
            return $this->isCodeInRange((int) $channel['channel_code']);
        }

        return $channel['capacity'] > 0 && $this->isCodeInRange((int) $channel['channel_code']);
    }

    private function getValidVendChannel($channel)
    {
        if ($channel['capacity'] > 0 && $this->isCodeInRange((int) $channel['channel_code'])) {
            return true;
        }
    }

    /**
     * Vending / freezer boards address channels 10–69 (<row><column>). A CityBox
     * chiller uses three digits, <layer><position 2 digits> = 101–699, because a
     * layer can hold more than nine SKUs (ChillerPlanogram). Anything else is
     * inactive noise from a board.
     */
    private function isCodeInRange(int $code): bool
    {
        if ($this->vend->isSmartChiller()) {
            return \App\Services\Citybox\ChillerPlanogram::isChillerCode($code);
        }

        return $code >= 10 && $code <= 69;
    }

    private function syncVendChannelRecordVMCBeforeQty(VendChannelRecord $vendChannelRecord)
    {
        $this->syncVendChannelRecordVMCQty($vendChannelRecord, $vendChannelRecord->before_data_json, 'vmc_before_qty');
    }

    private function syncVendChannelRecordVMCAfterQty(VendChannelRecord $vendChannelRecord)
    {
        $this->syncVendChannelRecordVMCQty($vendChannelRecord, $vendChannelRecord->after_data_json, 'vmc_after_qty');
    }

    /**
     * Carry a frame's qty onto the ops-job rows, when this record belongs to an
     * item. The completion path in OpsJobController fills whichever frames have
     * landed by then; this fills the one that arrives afterwards. Both go
     * through OpsJobFrameQty so they cannot drift apart again.
     */
    private function syncVendChannelRecordVMCQty(VendChannelRecord $vendChannelRecord, ?array $frame, string $column): void
    {
        $item = $vendChannelRecord->opsJobItem;

        if ($item && $item->opsJobItemChannels()->exists()) {
            OpsJobFrameQty::apply($item, $frame, $column, skuStocked: $this->vend->isSkuStocked());
        }
    }

    private function getChannelErrorRatesArray($vendId)
    {
        $sixDaysAgo = Carbon::today()->subDays(6)->startOfDay()->toDateTimeString();
        $oneDayAgo = Carbon::today()->subDays(1)->startOfDay()->toDateTimeString();
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();

        // One fault rule (DispenseVerdict): 0/6 are clean drops, 99 is "no TRADE", never a fault.
        $fault = DispenseVerdict::sqlFaultId('vend_channel_error_id');
        $itemFault = DispenseVerdict::sqlFault('vend_transaction_items.vend_channel_error_code');

        $singleData = \App\Models\VendTransaction::query()
            ->where('vend_id', $vendId)
            ->whereNotNull('vend_channel_id')
            ->where('is_multiple', false)
            ->where('transaction_datetime', '>=', $sixDaysAgo)
            ->selectRaw('
                vend_channel_id,
                COUNT(id) as seven_days_total_count,
                COUNT(CASE WHEN '.$fault.' THEN 1 END) as seven_days_error_count,
                COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as two_days_total_count,
                COUNT(CASE WHEN transaction_datetime >= ? AND '.$fault.' THEN 1 END) as two_days_error_count,
                COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as one_day_total_count,
                COUNT(CASE WHEN transaction_datetime >= ? AND '.$fault.' THEN 1 END) as one_day_error_count
            ', [$oneDayAgo, $oneDayAgo, $todayStart, $todayStart])
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
                COUNT(CASE WHEN '.$itemFault.' THEN 1 END) as seven_days_error_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as two_days_total_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND '.$itemFault.' THEN 1 END) as two_days_error_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as one_day_total_count,
                COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND '.$itemFault.' THEN 1 END) as one_day_error_count
            ', [$oneDayAgo, $oneDayAgo, $todayStart, $todayStart])
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
        $twoDaysTotal = ($singleData->two_days_total_count ?? 0) + ($multiData->two_days_total_count ?? 0);
        $twoDaysError = ($singleData->two_days_error_count ?? 0) + ($multiData->two_days_error_count ?? 0);
        $oneDayTotal = ($singleData->one_day_total_count ?? 0) + ($multiData->one_day_total_count ?? 0);
        $oneDayError = ($singleData->one_day_error_count ?? 0) + ($multiData->one_day_error_count ?? 0);

        return [
            'seven_days_total_count' => $sevenDaysTotal,
            'seven_days_error_count' => $sevenDaysError,
            'seven_days_error_rate' => $sevenDaysTotal > 0 ? round(($sevenDaysError / $sevenDaysTotal) * 100, 2) : 0,
            'two_days_total_count' => $twoDaysTotal,
            'two_days_error_count' => $twoDaysError,
            'two_days_error_rate' => $twoDaysTotal > 0 ? round(($twoDaysError / $twoDaysTotal) * 100, 2) : 0,
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
