<?php

namespace App\Models;

use App\Models\Scopes\OperatorDeliveryProductMappingVendFilterScope;
use App\Traits\GetUserTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingVend extends Model
{
    use GetUserTimezone;
    // use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorDeliveryProductMappingVendFilterScope);
    }

    protected $table = 'delivery_product_mapping_vend';

    protected $fillable = [
        'customer_id',
        'delivery_platform_ref_number_id',
        'delivery_product_mapping_id',
        'delivery_product_mapping_vend_channels_json',
        'end_date',
        'is_active',
        'last_menu_json',
        'platform_ref_id',
        'start_date',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'delivery_product_mapping_vend_channels_json' => 'json',
        'end_date' => 'datetime',
        'last_menu_json' => 'json',
        'start_date' => 'datetime',
    ];

    // relationships
    public function deliveryPlatformCampaignItemVends()
    {
        return $this->hasMany(DeliveryPlatformCampaignItemVend::class);
    }

    public function deliveryPlatformOrders()
    {
        return $this->hasMany(DeliveryPlatformOrder::class);
    }

    public function deliveryPlatformRefNumber()
    {
        return $this->belongsTo(DeliveryPlatformRefNumber::class);
    }

    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }

    public function deliveryProductMappingVendChannels()
    {
        return $this->hasMany(DeliveryProductMappingVendChannel::class)->orderBy('vend_channel_code');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        $query = $query
            ->when($request->delivery_platform_operator_id, function($query, $search) {
                if($search != 'all') {
                    $query->whereHas('deliveryProductMapping', function($query) use ($search) {
                        $query->where('delivery_platform_operator_id', $search);
                    });
                }
            })
            ->when($request->delivery_product_mapping_id, function($query, $search) {
                if($search != 'all') {
                    $query->where('delivery_product_mapping_id', $search);
                }
            })
            ->when($request->delivery_platform_type_id, function($query, $search) {
                if($search != 'all') {
                    $query->whereHas('deliveryProductMapping.deliveryPlatformOperator', function($query) use ($search) {
                        $query->where('type', $search);
                    });
                }
            })
            ->when($request->operators, function($query, $search) {
                if(!in_array('all', $search)){
                    $query->whereHas('deliveryProductMapping', function($query) use ($search) {
                        $query->whereIn('operator_id', $search);
                    });
                }
            })
            ->when($request->platform_ref_id, function($query, $search) {
                $query->where('platform_ref_id', 'LIKE', "{$search}%");
            })
            ->when($request->vend_code, function($query, $search) use ($request) {
                $query->where('vend_code', 'LIKE', "{$search}%");
            })
            ->when($request->is_active, function($query, $search) use ($request) {
                // dd($request->all());
                if($search != 'all') {
                    if($search == 'true') {
                        $query->whereNull('end_date');
                    } else {
                        $query->whereNotNull('end_date');
                    }
                }
            });

        return $query;
    }
}
