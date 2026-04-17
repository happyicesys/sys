<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Models\VoucherItem;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncVoucherStatus implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $today = Carbon::today();

        $vouchers = Voucher::with('voucherItems')
            ->where('status', Voucher::STATUS_ACTIVE)
            ->where('date_to', '<', $today)
            ->get();

        if ($vouchers->isEmpty()) {
            return;
        }

        // Batch-expire all non-redeemed voucher items in a single query
        $itemIds = $vouchers
            ->flatMap(fn($v) => $v->voucherItems)
            ->where('status', '!=', Voucher::STATUS_REDEEMED)
            ->pluck('id');

        if ($itemIds->isNotEmpty()) {
            VoucherItem::whereIn('id', $itemIds)->update([
                'status'     => Voucher::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
        }

        // Batch-expire all fetched vouchers in a single query
        Voucher::whereIn('id', $vouchers->pluck('id'))->update([
            'status'     => Voucher::STATUS_EXPIRED,
            'updated_at' => now(),
        ]);
    }
}
