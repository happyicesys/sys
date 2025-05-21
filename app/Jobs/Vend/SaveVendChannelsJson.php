<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveVendChannelsJson implements ShouldQueue
{
    //
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 5;

    protected $originalVendChannelData;
    protected $vendId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($vendId, $originalVendChannelData = null)
    {
        $this->originalVendChannelData = $originalVendChannelData;
        $this->vendId = $vendId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vend = Vend::with([
            'vendChannels.product.thumbnail'
            ])->findOrFail($this->vendId);

        $vendTotals = [
            'vendChannelsTotalQtyWithoutClaw' => $vend->vendChannelsTotalQtyWithoutClaw,
            'vendChannelsTotalCapacityWithoutClaw' => $vend->vendChannelsTotalCapacityWithoutClaw,
            'vendChannelsOutOfStock' => $vend->vendChannelsOutOfStock,
            'vendChannelsErrorLogsActive' => $vend->vendChannelsErrorLogsActive,
            'vendChannelsCount' => $vend->vendChannelsCount,
        ];
        $totals = [
            'qty'
                => $vendTotals['vendChannelsTotalQtyWithoutClaw'],
            'capacity'
                => $vendTotals['vendChannelsTotalCapacityWithoutClaw'],
            'sales'
                => $vendTotals['vendChannelsTotalCapacityWithoutClaw'] - $vendTotals['vendChannelsTotalQtyWithoutClaw'],
            'balancePercent'
                => $vendTotals['vendChannelsTotalCapacityWithoutClaw'] ? round($vendTotals['vendChannelsTotalQtyWithoutClaw']/ $vendTotals['vendChannelsTotalCapacityWithoutClaw'] * 100) : 0,
            'outOfStock'
                => $vendTotals['vendChannelsOutOfStock'],
            'activeErrorLogs'
                => $vendTotals['vendChannelsErrorLogsActive'],
            'count'
                => $vendTotals['vendChannelsCount'],
            'outOfStockSku'
                => $vendTotals['vendChannelsOutOfStock'] + $vendTotals['vendChannelsErrorLogsActive'],
            'outOfStockSkuPercent'
                => $vendTotals['vendChannelsCount'] ? round(($vendTotals['vendChannelsOutOfStock'] + $vendTotals['vendChannelsErrorLogsActive'])/ $vendTotals['vendChannelsCount'] * 100) : 0,
        ];

        $vend->update([
            'original_vend_channels_json' => $this->originalVendChannelData,
            'vend_channels_json' => $vend->vendChannels->map(function ($channel) use ($vend) {

                $sellingPriceType = $vend->customer?->selling_price_type;
                $sellingPrice = $channel->product?->sellingPrices
                    ?->firstWhere('type', $sellingPriceType);

                return [
                    'id' => $channel->id,
                    'amount' => $channel->amount/ 100,
                    'amount2' => $channel->amount2/ 100,
                    'code' => $channel->code,
                    'discount_group' => $channel->discount_group,
                    'error_rate_json' => $channel->error_rate_json,
                    'sku_code' => $channel->sku_code,
                    'qty' => $channel->qty,
                    'capacity' => $channel->capacity,
                    'product' => $channel->product ? [
                        'id' => $channel->product->id,
                        'code' => $channel->product->code,
                        'name' => $channel->product->name,
                        'thumbnail' => $channel->product->thumbnail ? $channel->product->thumbnail->only(['id', 'full_url', 'modelable_id', 'modelable_type', 'type']) : null,
                        'is_available' => $channel->product->is_available,
                    ] : null,
                    'qty_sold_at' => $channel->qty_sold_at,
                    'last_stock_in_qty' => $channel->latestOpsJobItemChannel?->actual_qty ?? null,
                    'server_amount' => $channel->server_amount ?? null,
                    'ref_price' => $sellingPrice ? $sellingPrice->amount / 100 : null,
                    'qty_sold_at_date_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->format('ymd') : null,
                    'qty_sold_at_time_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->format('h:i a') : null,
                    'qty_sold_at_human_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->shortRelativeDiffForHumans() : null,

                ];
            }),
            'vend_channel_totals_json' => collect($totals),
        ]);
    }
}
