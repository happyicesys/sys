<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'txn_date',
        'operator_id',
        'vend_id',
        'customer_id',
        'product_id',
        'category_id',
        'category_group_id',
        'customer_location_type_id',
        'transaction_location_type_id',
        'vend_prefix_id',
        'vend_contract_id',
        'vend_model_id',
        'is_multiple',
        'is_binded_customer',
        'sale_count',
        'transaction_count',
        'success_count',
        'error_count',
        'error_count_no_4_5',
        'error_count_4_5',
        'amount_cents',
        'revenue_cents',
        'gross_profit_cents',
        'unit_cost_cents',
    ];

    protected $casts = [
        'txn_date' => 'date',
        'is_multiple' => 'boolean',
        'is_binded_customer' => 'boolean',
    ];
}
