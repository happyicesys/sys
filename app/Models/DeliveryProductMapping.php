<?php

namespace App\Models;

use App\Models\Scopes\OperatorActiveScope;
use App\Models\Scopes\OperatorDeliveryProductMappingScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMapping extends Model
{
    use HasFactory;

    protected static function booted()
    {
        // Was OperatorVendFilterScope, commented out because it emits
        // `vends.operator_id` and there is no vends table in this query to bind
        // it to - it can only ever have thrown. The replacement expresses the
        // rule this table actually needs; see the scope class.
        static::addGlobalScope(new OperatorDeliveryProductMappingScope);
    }

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

    // protected $with = ['operator.country'];

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
        // Deactivating an operator must not hide it from its own historical
        // mapping rows: OperatorActiveScope would resolve this to null and
        // every downstream ->operator->country chain (currency exponent) would
        // fatal. The scope is a listing filter, not a data-integrity rule.
        return $this->belongsTo(Operator::class)->withoutGlobalScope(OperatorActiveScope::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function deliveryPlatformCampaign()
    {
        return $this->hasOne(DeliveryPlatformCampaign::class);
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
        })
        ->when($request->platform_ref_id, function($query, $search) {
            $query->whereHas('deliveryProductMappingVends', function($query) use ($search) {
                $query->where('platform_ref_id', 'LIKE', "{$search}%");
            });
        });

        return $query;
    }
}
