<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannelStockEvent extends Model
{
    use HasFactory;

    public const TYPE_SOLD_OUT = 'sold_out';
    public const TYPE_RESTOCKED = 'restocked';

    protected $fillable = [
        'vend_channel_id',
        'vend_id',
        'product_id',
        'event_type',
        'qty_before',
        'qty_after',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
