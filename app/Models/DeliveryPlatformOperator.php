<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformOperator extends Model
{
    use HasFactory;

    const TYPE_SANDBOX = 'sandbox';
    const TYPE_PRODUCTION = 'production';

    protected $fillable = [
        'delivery_platform_id',
        'operator_id',
        'field1',
        'field2',
        'field3',
        'field4',
        'type',
    ];

    // relationships
    public function deliveryPlatform()
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
