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
        ])->find($this->vendId);

        if (!$vend) {
            // Vend no longer exists (may have been deleted), skip silently
            return;
        }

        $vendChannels = $vend->vendChannels;
        // Derive "without claw" (channel codes 50-59 excluded) from the already
        // loaded vendChannels instead of firing a second identical query. The
        // vendChannels relation already scopes is_active + capacity > 0, which is
        // exactly the base scope of vendChannelsWithoutClaw.
        $vendChannelsWithoutClaw = $vendChannels->reject(function ($channel) {
            return $channel->code >= 50 && $channel->code <= 59;
        });
        $productLimitLookup = $this->resolveProductLimits($vendChannels);


        // A channel is unsellable when it is empty OR error-locked. Count the
        // CHANNELS in that state, not the two conditions separately: summing
        // outOfStock + activeErrorLogs double-counted any channel that was both,
        // and counted a channel once per uncleared error log, so outOfStockSku
        // could exceed the channel count and push "Remaining Channel#" negative.
        $vendChannelsErrorLocked = $vendChannels->filter(function ($channel) {
            return $this->hasActiveError($channel);
        });

        $vendTotals = [
            'vendChannelsTotalQtyWithoutClaw' => $vendChannelsWithoutClaw->sum('qty'),
            'vendChannelsTotalCapacityWithoutClaw' => $vendChannelsWithoutClaw->sum('capacity'),
            'vendChannelsOutOfStock' => $vendChannels->where('qty', 0)->count(),
            'vendChannelsErrorLogsActive' => $this->calculateActiveErrorLogs($vendChannels),
            'vendChannelsErrorLocked' => $vendChannelsErrorLocked->count(),
            // Error-locked channels that still hold stock — the ones that add to
            // the unsellable total on top of the empty ones. Empty channels that
            // also carry an error are already covered by vendChannelsOutOfStock.
            'vendChannelsErrorLockedInStock' => $vendChannelsErrorLocked->where('qty', '>', 0)->count(),
            'vendChannelsCount' => $vendChannels->count(),
        ];

        $vendChannelsUnsellable = $vendTotals['vendChannelsOutOfStock'] + $vendTotals['vendChannelsErrorLockedInStock'];

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
            // Raw uncleared-error-log count, kept for diagnostics. It can exceed
            // errorLockedSku when one channel carries several uncleared errors —
            // use errorLockedSku, not this, for anything channel-based.
            'activeErrorLogs'
            => $vendTotals['vendChannelsErrorLogsActive'],
            'errorLockedSku'
            => $vendTotals['vendChannelsErrorLocked'],
            'errorLockedInStockSku'
            => $vendTotals['vendChannelsErrorLockedInStock'],
            'count'
            => $vendTotals['vendChannelsCount'],
            'outOfStockSku'
            => $vendChannelsUnsellable,
            'outOfStockSkuPercent'
            => $vendTotals['vendChannelsCount'] ? round($vendChannelsUnsellable / $vendTotals['vendChannelsCount'] * 100) : 0,
        ];

        $vend->update(array_merge(
            // Only SyncVendChannels passes the raw machine payload; the other
            // dispatch sites re-derive the totals from the DB and have nothing
            // to record here. Writing null unconditionally let any of them wipe
            // the last payload we captured, so leave the column alone instead.
            $this->originalVendChannelData !== null
                ? ['original_vend_channels_json' => $this->originalVendChannelData]
                : [],
            [
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
                        'limit_qty' => $productLimitLookup->get($channel->product->id)['qty'] ?? 0,
                        'limit_is_created_by_system' => $productLimitLookup->get($channel->product->id)['is_created_by_system'] ?? null,
                    ] : null,
                    'last_stock_in_qty' => $channel->latestOpsJobItemChannel?->actual_qty ?? null,
                    'server_amount' => $channel->server_amount ? $channel->server_amount / 100 : null,
                    'ref_price' => $sellingPrice ? $sellingPrice->amount / 100 : null,
                    'qty_sold_at_date_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->format('ymd') : null,
                    'qty_sold_at_time_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->format('h:i a') : null,
                    'qty_sold_at_human_formatted' => $channel->qty_sold_at ? $channel->qty_sold_at->shortRelativeDiffForHumans() : null,
                    'vendChannelErrorLogs' => $channel->vendChannelErrorLogs?->whereNotNull('vend_transaction_id')->values()->map(function ($errorLog) {
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
            ]
        ));
    }

    private function calculateActiveErrorLogs($vendChannels)
    {
        return $vendChannels->reduce(function ($carry, $vendChannel) {
            return $carry + $vendChannel->vendChannelErrorLogs
                ->filter(function ($errorLog) {
                    return $this->isActiveError($errorLog);
                })
                ->count();
        }, 0);
    }

    // Does this channel currently count as error-locked? Codes 4, 5 and 7 are
    // excluded because they are recoverable/informational and do not stop the
    // channel from vending.
    private function hasActiveError($vendChannel)
    {
        return $vendChannel->vendChannelErrorLogs->contains(function ($errorLog) {
            return $this->isActiveError($errorLog);
        });
    }

    private function isActiveError($errorLog)
    {
        return !$errorLog->is_error_cleared
            && !in_array($errorLog->vendChannelError->code, [4, 5, 7]);
    }

    private function resolveProductLimits($vendChannels)
    {
        $productIds = $vendChannels->pluck('product_id')->filter()->unique();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $tomorrow = Carbon::today()->addDay();

        return ProductLimit::query()
            ->select('product_id', 'qty', 'is_created_by_system', 'created_at')
            ->whereIn('product_id', $productIds)
            ->where('date', $tomorrow->toDateString())
            ->orderBy('product_id')
            ->orderByDesc('created_at')
            ->get()
            ->unique('product_id')
            ->mapWithKeys(function ($productLimit) {
                return [
                    $productLimit->product_id => [
                        'qty' => $productLimit->qty,
                        'is_created_by_system' => $productLimit->is_created_by_system
                    ]
                ];
            });
    }

}
