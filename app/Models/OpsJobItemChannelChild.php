<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-flavour ledger row under a blind parent's ops_job_item_channels row.
 * See migration create_ops_job_item_channel_children_table.
 */
class OpsJobItemChannelChild extends Model
{
    use HasFactory;

    protected $table = 'ops_job_item_channel_children';

    protected $fillable = [
        'ops_job_item_channel_id',
        'child_product_id',
        'weight_pct',
        'to_pick_qty',
        'picked_qty',
        'actual_qty',
        'picked_before_qty',
        'sort',
    ];

    protected $casts = [
        'weight_pct' => 'integer',
        'to_pick_qty' => 'integer',
        'picked_qty' => 'integer',
        'actual_qty' => 'integer',
        'picked_before_qty' => 'integer',
        'sort' => 'integer',
    ];

    public function opsJobItemChannel()
    {
        return $this->belongsTo(OpsJobItemChannel::class);
    }

    public function childProduct()
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }
}
