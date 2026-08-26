<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'default_payment_method_id',
        'remarks',
        'key1_name',
        'key2_name',
        'key3_name',
    ];

    /**
     * Whether mark1 can return this gateway's money by API call.
     *
     * THE single definition of "refundable gateway": the settlement resolver
     * (VendTransactionService::applyTradeToPreCreatedRow) demotes a failed
     * single-item vend to PENDING only when this is true, and
     * HandleFailedVendTransaction dispatches the refund job on the same test.
     * The two must never disagree — a gateway that demotes but never refunds
     * leaves rows forever-PENDING (out of sales AND never refunded). Widen this
     * together with a dispatch branch when Fiuu/Midtrans API refunds land.
     */
    public function supportsApiRefund(): bool
    {
        return strtolower((string) $this->name) === 'omise';
    }

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
