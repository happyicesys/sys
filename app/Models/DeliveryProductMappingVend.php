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

    // Why a period ended (Grab Stores → Remove / Move).
    const UNBIND_REASONS = [
        'moved' => 'Moved to another machine',
        'replaced' => 'Machine replaced',
        'store_closed' => 'Store closed',
        'correction' => 'Correction',
        'template' => 'Template changed',
        'removed' => 'Removed',
    ];

    protected $fillable = [
        'bound_by',
        'customer_id',
        'delivery_platform_ref_number_id',
        'delivery_product_mapping_id',
        'delivery_product_mapping_vend_channels_json',
        'end_date',
        'is_active',
        'last_menu_json',
        'pause_reason',
        'paused_until',
        'platform_ref_id',
        'start_date',
        'unbind_reason',
        'unbind_remarks',
        'unbound_by',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'delivery_product_mapping_vend_channels_json' => 'json',
        'end_date' => 'datetime',
        'last_menu_json' => 'json',
        'paused_until' => 'datetime',
        'start_date' => 'datetime',
    ];

    public function boundBy()
    {
        return $this->belongsTo(User::class, 'bound_by');
    }

    public function unboundBy()
    {
        return $this->belongsTo(User::class, 'unbound_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

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

    // A store sells on Grab only while its binding is open and on AND its ref number
    // (Delivery Platform Ref Numbers page) is not Inactive (Brian, 2026-10-29): the
    // ref page is where ops switch a store off, so Inactive alone must stop it.
    public function isSelling(): bool
    {
        return ! $this->end_date
            && $this->is_active
            && ! $this->isRefNumberInactive();
    }

    public function isRefNumberInactive(): bool
    {
        return $this->delivery_platform_ref_number_id
            && (int) $this->deliveryPlatformRefNumber?->status === DeliveryPlatformRefNumber::STATUS_INACTIVE;
    }

    // scopes
    public function scopeSelling($query)
    {
        return $query->whereNull('end_date')
            ->where('is_active', true)
            ->refNumberNotInactive();
    }

    // Raw (NOT) EXISTS, not whereDoesntHave: a scoped relation would read "no Inactive
    // ref that I may see". A row with no ref number linked is never Inactive.
    public function scopeRefNumberNotInactive($query)
    {
        return $query->whereNotExists(self::inactiveRefNumber(...));
    }

    public function scopeOrRefNumberInactive($query)
    {
        return $query->orWhereExists(self::inactiveRefNumber(...));
    }

    private static function inactiveRefNumber($ref): void
    {
        $ref->selectRaw('1')
            ->from('delivery_platform_ref_numbers')
            ->whereColumn('delivery_platform_ref_numbers.id', 'delivery_product_mapping_vend.delivery_platform_ref_number_id')
            ->where('delivery_platform_ref_numbers.status', DeliveryPlatformRefNumber::STATUS_INACTIVE);
    }

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
