<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'classname',
        'country_id',
        'default_payment_method_id',
        'remarks',
        'key1_name',
        'key2_name',
        'key3_name',
    ];

    // relationships
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function defaultPaymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function operatorPaymentGateways()
    {
        return $this->hasMany(OperatorPaymentGateway::class);
    }
}
