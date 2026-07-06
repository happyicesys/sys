<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundSettlementExport extends Model
{
    use HasFactory;

    const FORMAT_CIMB_TXT = 'cimb_txt';
    const FORMAT_XLSX = 'xlsx';

    protected $fillable = [
        'refund_payout_batch_id',
        'method',
        'format',
        'file_path',
        'count',
        'total_cents',
        'exported_by',
        'exported_at',
    ];

    protected $casts = [
        'count' => 'integer',
        'total_cents' => 'integer',
        'exported_at' => 'datetime',
    ];

    public function settlement()
    {
        return $this->belongsTo(RefundPayoutBatch::class, 'refund_payout_batch_id');
    }
}
