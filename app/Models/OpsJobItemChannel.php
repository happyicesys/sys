<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJobItemChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'actual_qty',
        'capacity',
        'is_error_settle',
        'ops_job_id',
        'ops_job_item_id',
        'picked_qty',
        'product_id',
        'vend_channel_id',
        'vend_channel_code',
        'vend_code',
        'vmc_before_qty',
        'vmc_after_qty',
    ];

    // relationships
    public function opsJob()
    {
        return $this->belongsTo(OpsJob::class);
    }

    public function opsJobItem()
    {
        return $this->belongsTo(OpsJobItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }
}
