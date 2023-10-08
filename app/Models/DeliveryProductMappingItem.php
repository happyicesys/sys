<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'delivery_product_mapping_id',
        'product_id',
        'product_mapping_id',
        'product_mapping_item_id',
        'sub_category_json',
    ];

    protected $casts = [
        'sub_category_json' => 'json',
    ];

    // getter and setter
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ (10^$this->deliveryProductMapping->operator->country->currency_exponent),
            set: fn (string $value) => $value * (10^$this->deliveryProductMapping->operator->country->currency_exponent),
        );
    }

    // relationships
    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function productMappingItem()
    {
        return $this->belongsTo(ProductMappingItem::class);
    }
}
