<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTransactionItemArchive extends Model
{
    use HasFactory;

    protected $table = 'vend_transaction_items_bk';

    protected $fillable = [
        'created_at',
        'id',
        'is_refunded',
        'product_id',
        'product_json',
        'unit_cost',
        'unit_cost_id',
        'updated_at',
        'vend_channel_id',
        'vend_channel_code',
        'vend_channel_error_id',
        'vend_channel_error_json',
        'vend_transaction_id',
    ];

    protected $casts = [
        'vend_channel_error_json' => 'json',
    ];
}
