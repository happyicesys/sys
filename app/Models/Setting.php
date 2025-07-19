<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    // customer index view
    const CUSTOMER_INDEX = [
        'actual_stock_in_value' => [
            'red' => [
                'operator' => '<',
                'value' => 150
            ]
        ]
    ];

    protected $fillable = [
        'access_all_operator_ids_array',
        'customer_index_json',
        'payment_gateway_log_refund_scanned_at',
    ];

    protected $casts = [
        'access_all_operator_ids_array' => 'array',
        'customer_index_json' => 'json',
        'payment_gateway_log_refund_scanned_at' => 'datetime'
    ];
}
