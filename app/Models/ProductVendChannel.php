<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVendChannel extends Model
{
    use HasFactory;

    protected $table = 'product_vend_channels';

    protected $fillable = [
        'product_id',
        'channel_count',
        'date',
        'year',
        'month',
        'day',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
