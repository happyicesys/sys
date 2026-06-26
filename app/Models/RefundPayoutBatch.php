<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundPayoutBatch extends Model
{
    use HasFactory;

    const STATUS_GENERATED = 'generated';
    const STATUS_UPLOADED = 'uploaded';

    protected $fillable = [
        'reference',
        'method',
        'created_by',
        'csv_path',
        'count',
        'total_cents',
        'status',
        'uploaded_at',
    ];

    protected $casts = [
        'count' => 'integer',
        'total_cents' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function tickets()
    {
        return $this->hasMany(RefundTicket::class, 'payout_batch_id');
    }
}
