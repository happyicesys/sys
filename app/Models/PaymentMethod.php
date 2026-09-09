<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    /** `code` of the Card Terminal method (NETS reader). The ONLY method the NETS settlement report can carry. */
    public const CODE_CARD_TERMINAL = 1;

    use HasFactory;

    protected $fillable = [
        'code',
        'is_active',
        'is_apk_constant',
        'name',
        'payment_gateway_id',
        'payment_merchant_id',
        'sequence',
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
