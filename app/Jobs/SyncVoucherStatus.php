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

        foreach ($vouchers as $voucher) {
            $voucher->status = Voucher::STATUS_EXPIRED;

            foreach ($voucher->voucherItems as $voucherItem) {
                if ($voucherItem->status !== VoucherItem::STATUS_REDEEMED) {
                    $voucherItem->status = VoucherItem::STATUS_EXPIRED;
                    $voucherItem->save();
                }
            }

            $voucher->save();
        }
    }
}
