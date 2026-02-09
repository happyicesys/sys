<?php

namespace App\Jobs\Vend;

use App\Models\ProductLimit;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveVendChannelsJson implements ShouldQueue, ShouldBeUnique
{
    //
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;

    // Prevent duplicate jobs for same vend for 3 minutes
    public $uniqueFor = 180;

    public function uniqueId()
    {
        return 'vend_' . $this->vendId;
    }

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
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->with([
            'customer',
            'vendChannels.product.thumbnail',
            'vendChannels.product.sellingPrices',
            'vendChannels.latestOpsJobItemChannel',
            'vendChannels.vendChannelErrorLogs.vendChannelError',
            'vendChannelsWithoutClaw',
        ])->findOrFail($this->vendId);

        $vendChannelsWithoutClaw = $vend->vendChannelsWithoutClaw;
        $vendChannels = $vend->vendChannels;
        $productLimitLookup = $this->resolveProductLimits($vendChannels);


        $vendTotals = [
            'vendChannelsTotalQtyWithoutClaw' => $vendChannelsWithoutClaw->sum('qty'),
            'vendChannelsTotalCapacityWithoutClaw' => $vendChannelsWithoutClaw->sum('capacity'),
            'vendChannelsOutOfStock' => $vendChannels->where('qty', 0)->count(),
            'vendChannelsErrorLogsActive' => $this->calculateActiveErrorLogs($vendChannels),
            'vendChannelsCount' => $vendChannels->count(),
        ];

        $totals = [
            'qty'
            => $vendTotals['vendChannelsTotalQtyWithoutClaw'],
            'capacity'
            => $vendTotals['vendChannelsTotalCapacityWithoutClaw'],
            'sales' => max(0, $vendTotals['vendChannelsTotalCapacityWithoutClaw'] - $vendTotals['vendChannelsTotalQtyWithoutClaw']),
            'balancePercent'
            => $vendTotals['vendChannelsTotalCapacityWithoutClaw'] ? round($vendTotals['vendChannelsTotalQtyWithoutClaw'] / $vendTotals['vendChannelsTotalCapacityWithoutClaw'] * 100) : 0,
            'outOfStock'
            => $vendTotals['vendChannelsOutOfStock'],
            'activeErrorLogs'
            => $vendTotals['vendChannelsErrorLogsActive'],
            'count'
            => $vendTotals['vendChannelsCount'],
            'outOfStockSku'
            => $vendTotals['vendChannelsOutOfStock'] + $vendTotals['vendChannelsErrorLogsActive'],
            'outOfStockSkuPercent'
            => $vendTotals['vendChannelsCount'] ? round(($vendTotals['vendChannelsOutOfStock'] + $vendTotals['vendChannelsErrorLogsActive']) / $vendTotals['vendChannelsCount'] * 100) : 0,
        ];

        $vend->update([
            'original_vend_channels_json' => $this->originalVendChannelData,
            'vend_channels_json' => $vendChannels->map(function ($channel) use ($vend, $productLimitLookup) {
                $sellingPriceType = $vend->customer?->selling_price_type;
                $sellingPrice = $channel->product?->sellingPrices
                        ?->firstWhere('type', $sellingPriceType);

                return [
                    'id' => $channel->id,
                    'amount' => $channel->amount / 100,
                    'amount2' => $channel->amount2 / 100,
                    'code' => $channel->code,
                    'discount_group' => $channel->discount_group,
                    'error_rate_json' => $channel->error_rate_json,
                    'sku_code' => $channel->sku_code,
                    'qty' => $channel->qty,
                    'capacity' => $channel->capacity,
                    'is_active' => $channel->is_active,
                    'product' => $channel->product ? [
                        'id' => $channel->product->id,
                        'code' => $channel->product->code,
                        'name' => $channel->product->name,
                        'thumbnail' => $channel->product->thumbnail ? $channel->product->thumbnail->only(['id', 'full_url', 'modelable_id', 'modelable_type', 'type']) : null,
                        'is_available' => $channel->product->is_available,
                        'limit_qty' => $productLimitLookup->get($channel->product->id),
                    ] : null,
                    'last_stock_in_qty' => $channel->latestOpsJobItemChannel?->actual_qty ?? null,
                    'server_amount' => $channel->server_amount ? $channel->server_amount / 100 : null,
                    'ref_price' => $sellingPrice ? $sellingPrice->amount / 100 : null,
                    'qty_sold_at_date_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->format('ymd') : null,
                    'qty_sold_at_time_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->format('h:i a') : null,
                    'qty_sold_at_human_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->shortRelativeDiffForHumans() : null,
                    'vendChannelErrorLogs' => $channel->vendChannelErrorLogs?->map(function ($errorLog) {
                        return [
                            'id' => $errorLog->id,
                            'code' => $errorLog->vendChannelError->code,
                            'created_at' => $errorLog->created_at->format('ymd h:i a'),
                            'desc' => $errorLog->vendChannelError->desc,
                            'is_error_cleared' => $errorLog->is_error_cleared,
                        ];
                    }),
                ];
            }),
            'vend_channel_totals_json' => $totals,
        ]);
    }

    private function calculateActiveErrorLogs($vendChannels)
    {
        return $vendChannels->reduce(function ($carry, $vendChannel) {
            $activeErrorCount = $vendChannel->vendChannelErrorLogs->reduce(function ($innerCarry, $errorLog) {
                if (
                    !$errorLog->is_error_cleared &&
                    !in_array($errorLog->vendChannelError->code, [4, 5, 7])
                ) {
                    $innerCarry++;
                }
                return $innerCarry;
            }, 0);
            return $carry + $activeErrorCount;
        }, 0);
    }

    private function resolveProductLimits($vendChannels)
    {
        $productIds = $vendChannels->pluck('product_id')->filter()->unique();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $today = Carbon::today();

        return ProductLimit::query()
            ->select('product_id', 'qty', 'created_at')
            ->whereIn('product_id', $productIds)
            ->whereDate('date', $today)
            ->orderBy('product_id')
            ->orderByDesc('created_at')
            ->get()
            ->unique('product_id')
            ->mapWithKeys(function ($productLimit) {
                return [$productLimit->product_id => $productLimit->qty];
            });
    }

}
