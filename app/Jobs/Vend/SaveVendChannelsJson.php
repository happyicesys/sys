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

    protected $vendId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($vendId)
    {
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
            'vend_channels_json' => $vend->vendChannels,
            'vend_channel_totals_json' => collect($totals),
        ]);
    }
}
