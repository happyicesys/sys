<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionSettlementExport extends Model
{
    use HasFactory;

    const FORMAT_CIMB_TXT = 'cimb_txt';

    protected $fillable = [
        'commission_settlement_id',
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
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }
}
