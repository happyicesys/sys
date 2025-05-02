<?php

namespace App\Console\Commands;

use App\Models\VoucherItem;
use App\Jobs\ReleaseVoucherLock;
use Illuminate\Console\Command;

class ReleaseVoucherLockEvery2Mins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:voucher-lock-every-2-mins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release Voucher Lock Every 2 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $voucherItems = VoucherItem::where('is_locked', true)
            ->where('locked_at', '<=', now()->subMinutes(2))
            ->get();

        foreach ($voucherItems as $voucherItem) {
            ReleaseVoucherLock::dispatch($voucherItem);
        }
    }
}
