<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_json',
        'delivery_platform_operator_id',
        'delivery_product_mapping_items_json',
        'name',
        'operator_id',
        'product_mapping_id',
        'remarks',
        'vends_json',
    ];

    protected $casts = [
        'category_json' => 'json',
        'delivery_product_mapping_items_json' => 'json',
        'vends_json' => 'json',
    ];

    // relationships
    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryProductMappingItems()
    {
        return $this->hasMany(DeliveryProductMappingItem::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function vends()
    {
        return $this->belongsToMany(Vend::class)->orderBy('code');
    }
}
