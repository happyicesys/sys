<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'begin_date',
        'termination_date',
        'is_active',
        'is_rental',
        'is_profit_sharing',
        'is_profit_sharing_percentage',
        'is_both_utility_comm',
        'product_unit_price',
        'rental',
        'profit_sharing',
        'utilities',
        'adjustment_rate',
        'is_pwp',
        'pwp_adjustment_rate',
        'customer_id',
        'vend_id',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
