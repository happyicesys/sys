<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingItem extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    protected $fillable = [
        'amount',
        'channel_code',
        'delivery_product_mapping_id',
        'is_active',
        'product_id',
        'product_mapping_id',
        'product_mapping_item_id',
        'sub_category_json',
    ];

    protected $casts = [
        'sub_category_json' => 'json',
    ];

    protected $with = ['deliveryProductMapping.operator.country'];

    // getter and setter
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ ($this->deliveryProductMapping ? pow(10, $this->deliveryProductMapping->operator->country->currency_exponent) : 100) ,
            set: fn (string $value) => $value * ($this->deliveryProductMapping ? pow(10, $this->deliveryProductMapping->operator->country->currency_exponent) : 100),
        );
    }

    // relationships
    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }

    public function deliveryProductMappingVendChannels()
    {
        return $this->hasMany(DeliveryProductMappingVendChannel::class);
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
