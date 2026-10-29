<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformRefNumber extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;

    const STATUS_INACTIVE = 99;

    const STATUS_MAPPINGS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    protected $fillable = [
        'created_at',
        'delivery_platform_id',
        'operator_id',
        'ref_number',
        'remarks',
        'status',
    ];

    public function deliveryPlatform()
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function deliveryProductMappingVends()
    {
        return $this->hasMany(DeliveryProductMappingVend::class);
    }

    public function currentDeliveryProductMappingVend()
    {
        return $this->hasOne(DeliveryProductMappingVend::class)
            ->whereNull('end_date')
            ->latest('id');
    }

    public function openDeliveryProductMappingVends()
    {
        return $this->hasMany(DeliveryProductMappingVend::class)
            ->whereNull('end_date')
            ->orderByDesc('id');
    }

    public function storeEvents()
    {
        return $this->hasMany(DeliveryPlatformStoreEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    // The part after Grab's "4-" country prefix: C7CCRTEJJYUJR6 and 4-C7CCRTEJJYUJR6 are
    // one store (both were typed on prod), so duplicates are checked on this.
    public static function coreRefNumber(string $refNumber): string
    {
        return strtoupper(preg_replace('/^\d+-/', '', trim($refNumber)));
    }
}
