<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundSettlementLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_payout_batch_id',
        'actor_id',
        'actor_label',
        'action',
        'note',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function settlement()
    {
        return $this->belongsTo(RefundPayoutBatch::class, 'refund_payout_batch_id');
    }
}
