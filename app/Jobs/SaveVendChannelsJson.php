<?php

namespace App\Jobs;

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
        $vend = Vend::findOrFail($this->vendId);

        $vendTotals = [
            'vendChannelsTotalQty' => $vend->vendChannelsTotalQty,
            'vendChannelsTotalCapacity' => $vend->vendChannelsTotalCapacity,
            'vendChannelsOutOfStock' => $vend->vendChannelsOutOfStock,
            'vendChannelsErrorLogsActive' => $vend->vendChannelsErrorLogsActive,
            'vendChannelsCount' => $vend->vendChannelsCount,
        ];
        $totals = [
            'qty'
                => $vendTotals['vendChannelsTotalQty'],
            'capacity'
                => $vendTotals['vendChannelsTotalCapacity'],
            'sales'
                => $vendTotals['vendChannelsTotalCapacity'] - $vendTotals['vendChannelsTotalQty'],
            'balancePercent'
                => $vendTotals['vendChannelsTotalCapacity'] ? round($vendTotals['vendChannelsTotalQty']/ $vendTotals['vendChannelsTotalCapacity'] * 100) : 0,
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
        dd($totals);

        $vend->update([
            'vend_channels_json' => $vend->vendChannels,
            'vend_channel_totals_json' => collect($totals),
        ]);
    }
}
