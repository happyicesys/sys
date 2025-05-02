<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseVoucherLock implements ShouldQueue
{
    use Queueable;

    protected $model;
    /**
     * Create a new job instance.
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->model instanceof VoucherItem) {
            $voucherItem = VoucherItem::find($this->model->id);
            $voucherItem->is_locked = false;
            $voucherItem->locked_at = null;
            $voucherItem->locked_by_vend_id = null;
            $voucherItem->save();
        }
    }
}
