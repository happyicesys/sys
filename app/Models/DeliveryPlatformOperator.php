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
        'input1',
        'input3',
        'input3',
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
