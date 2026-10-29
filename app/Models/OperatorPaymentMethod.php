<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One operator's choice for one payment method on its machines' QR menu (Brian, 2026-10-29). A row exists only
 * where the operator differs from the method's default (PaymentMethod::isShownByDefault), so an operator with no
 * rows gets the normal menu. App\Services\Payment\OperatorPaymentMenu is the only writer.
 */
class OperatorPaymentMethod extends Model
{
    protected $fillable = [
        'operator_id',
        'payment_method_id',
        'is_shown',
        'updated_by',
    ];

    protected $casts = [
        'is_shown' => 'boolean',
    ];

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
