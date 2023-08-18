<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMerchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_url',
        'mask_url',
    ];

    public function paymentGateways()
    {
        return $this->belongsToMany(PaymentGateway::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
