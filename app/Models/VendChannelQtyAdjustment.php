<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hand overwrite of a SKU-stocked machine's on-hand qty (see ChannelQtyAdjuster). */
class VendChannelQtyAdjustment extends Model
{
    public const SOURCE_SETTING_EDIT = 'setting_edit';

    protected $fillable = [
        'vend_id',
        'vend_channel_id',
        'product_id',
        'channel_label',
        'qty_before',
        'qty_after',
        'supplier_qty_after',
        'supplier_msg_id',
        'source',
        'user_id',
    ];

    protected $casts = [
        'qty_before' => 'integer',
        'qty_after' => 'integer',
        'supplier_qty_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendChannel(): BelongsTo
    {
        return $this->belongsTo(VendChannel::class);
    }
}
