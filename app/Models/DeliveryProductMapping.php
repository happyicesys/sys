<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_platform_operator_id',
        'name',
        'product_mapping_id',
        'remarks',
    ];

    // relationships
    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }
}
