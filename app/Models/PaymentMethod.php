<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'is_active',
        'is_apk_constant',
        'name',
        'payment_gateway_id',
        'payment_merchant_id',
        'type_name',
    ];

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function paymentMerchant()
    {
        return $this->belongsTo(PaymentMerchant::class);
    }
}
