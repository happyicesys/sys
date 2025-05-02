<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'locked_at',
        'locked_by_vend_id',
        'member_id',
        'is_active',
        'is_locked',
        'is_redeemed',
        'redeemed_at',
        'status',
        'metadata',
        'voucher_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_redeemed' => 'boolean',
        'redeemed_at' => 'datetime',
        'metadata' => 'json',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
