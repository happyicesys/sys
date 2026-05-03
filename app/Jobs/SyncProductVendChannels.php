<?php

namespace App\Jobs;

use App\Models\ProductVendChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncProductVendChannels
 *
 * Snapshots how many active vend channels each product has on a given date.
 *
 * Logic:
 *   For every vend_channel that is:
 *     - is_active = true
 *     - product_id IS NOT NULL
 *     - belongs to a vend where is_active = true AND is_disposed = false
 *
 *   Count the channels per product and upsert one row per
 *   (product_id, date) into product_vend_channels.
 *
 * This runs nightly (00:08) so that the Sales Report > Product tab can
 * display a "Channel Availability" column (sum of daily snapshots over
 * the selected date range).
 */
class SyncProductVendChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $date;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function handle(): void
    {
        $carbon = Carbon::parse($this->date);

        Log::channel('single')->info('[SyncProductVendChannels] starting', [
            'date' => $this->date,
        ]);

        // Count active vend channels per product across all active, non-disposed vends
        $rows = DB::table('vend_channels as vc')
            ->join('vends as v', 'vc.vend_id', '=', 'v.id')
            ->where('vc.is_active', true)
            ->whereNotNull('vc.product_id')
            ->where('v.is_active', true)
            ->where('v.is_disposed', false)
            ->selectRaw('vc.product_id, COUNT(vc.id) as channel_count')
            ->groupBy('vc.product_id')
            ->get();

        if ($rows->isEmpty()) {
            Log::channel('single')->info('[SyncProductVendChannels] no active channels found, skipping upsert', [
                'date' => $this->date,
            ]);
            return;
        }

        $upserted = 0;

        foreach ($rows as $row) {
            ProductVendChannel::updateOrCreate(
                [
                    'product_id' => $row->product_id,
                    'date'       => $this->date,
                ],
                [
                    'channel_count' => $row->channel_count,
                    'year'          => $carbon->year,
                    'month'         => $carbon->month,
                    'day'           => $carbon->day,
                ]
            );
            $upserted++;
        }

        Log::channel('single')->info('[SyncProductVendChannels] done', [
            'date'     => $this->date,
            'upserted' => $upserted,
        ]);
    }
}
