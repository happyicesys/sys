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
        'is_active',
        'name',
        'operator_id',
        'product_mapping_id',
        'remarks',
        'reserved_percent',
        'reserved_qty',
    ];

    protected $casts = [
        'category_json' => 'json',
        'delivery_product_mapping_items_json' => 'json',
    ];

    protected $with = ['operator.country'];

    // relationships
    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryProductMappingBulks()
    {
        return $this->hasMany(DeliveryProductMappingBulk::class);
    }

    public function deliveryProductMappingItems()
    {
        return $this->hasMany(DeliveryProductMappingItem::class)->orderBy('channel_code');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function deliveryProductMappingVends()
    {
        return $this->hasMany(DeliveryProductMappingVend::class);
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {

        $query = $query->when($request->name, function($query, $search) {
            $query->where('name', 'LIKE', "%{$search}%");
        })
        ->when($request->vend_code, function($query, $search) use ($request) {
            $query->whereHas('deliveryProductMappingVends.vend', function($query) use ($request) {
                $query->where('code', 'LIKE', "{$request->vend_code}%");
            });
        });

        return $query;
    }
}
