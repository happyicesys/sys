<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTransactionArchive extends Model
{
    use HasFactory;

    protected $table = 'vend_transactions_bk';

    protected $fillable = [
        'amount',
        'created_at',
        'customer_id',
        'customer_json',
        'error_code_normalized',
        'gross_profit',
        'gross_profit_margin',
        'gst_vat_rate',
        'id',
        'is_multiple',
        'is_payment_received',
        'is_refunded',
        'items_json',
        'location_type_json',
        'operator_id',
        'operator_json',
        'order_id',
        'payment_gateway_log_id',
        'payment_method_id',
        'product_id',
        'product_json',
        'revenue',
        'transaction_datetime',
        'unit_cost',
        'unit_cost_id',
        'updated_at',
        'vend_channel_code',
        'vend_channel_error_id',
        'vend_channel_id',
        'vend_id',
        'vend_json',
        'vend_transaction_items_json',
        'vend_transaction_json',
    ];

    protected $casts = [
        'customer_json' => 'json',
        'items_json' => 'json',
        'location_type_json' => 'json',
        'operator_json' => 'json',
        'product_json' => 'json',
        'transaction_datetime' => 'datetime',
        'vend_json' => 'json',
        'vend_transaction_json' => 'json',
        'vend_transaction_items_json' => 'json',
    ];
}
