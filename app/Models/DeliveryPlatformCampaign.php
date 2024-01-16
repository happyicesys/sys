<?php

namespace App\Models;

use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformCampaign extends Model
{
    use GetUserTimezone, HasFactory;

    protected $fillable = [
        'delivery_platform_operator_id',
        'delivery_product_mapping_id',
        'is_active',
        'name',
        'remarks',
    ];

    // relationships
    public function deliveryPlatformCampaignItems()
    {
        return $this->hasMany(DeliveryPlatformCampaignItem::class);
    }

    public function deliveryPlatformCampaignItemVends()
    {
        return $this->hasMany(DeliveryPlatformCampaignItemVend::class);
    }

    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {

        $query = $query
            ->when($request->delivery_platform_operator_id, function($query, $search) {
                if($search != 'all') {
                    $query->where('delivery_platform_operator_id', $search);
                }
            })
            ->when($request->vend_code, function($query, $search) use ($request) {
                $query->whereHas('deliveryProductMappingVend.vend', function($query) use ($request) {
                    $query->where('code', 'LIKE', "{$request->vend_code}%");
                });
            });
            // ->when($request->date_from, function ($query, $search) {
            //     $query->where('datetime_from', '>=', Carbon::parse($search)->startOfDay());
            // })
            // ->when($request->date_to, function ($query, $search) {
            //     $query->where('datetime_to', '<=', Carbon::parse($search)->endOfDay());
            // });

        return $query;
    }
}
