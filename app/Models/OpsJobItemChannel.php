<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJobItemChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'actual_before_qty',
        'actual_qty',
        'amount',
        'capacity',
        'error_settled_at',
        'is_error_settle',
        'is_manually_replaced',
        'is_upcoming_product',
        'ops_job_id',
        'ops_job_item_id',
        'picked_before_qty',
        'picked_qty',
        'product_id',
        'qty',
        'replaces_ops_job_item_channel_id',
        'saved_picked_qty',
        'vend_channel_id',
        'vend_channel_code',
        'vend_code',
        'vmc_before_qty',
        'vmc_after_qty',
    ];

    protected $casts = [
        'error_settled_at' => 'datetime',
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

    // Blind SKU: per-flavour ledger when this channel's product is a parent housing.
    public function children()
    {
        return $this->hasMany(OpsJobItemChannelChild::class)->orderBy('sort');
    }
}
