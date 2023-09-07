<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'remarks',
        'field1_name',
        'field2_name',
        'field3_name',
        'field4_name',
    ];

    // relationships
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function deliveryPlatformOperators()
    {
        return $this->hasMany(DeliveryPlatformOperator::class);
    }
}
