<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'stock_cost_amount',
        'stock_value_amount',
        'product_id',
        'qty_vend',
        'qty_warehouse',
        'unit_cost_amount',
    ];

    // mutator and accessor
    protected function stockCostAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ 100 ,
        );
    }

    protected function stockValueAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ 100 ,
        );
    }

    protected function unitCostAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ 100 ,
        );
    }

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }
}
