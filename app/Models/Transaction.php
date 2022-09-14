<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'total_amount',
        'subtotal_amount',
        'total_qty',
        'deals_obj',
        'customer_id',
        'order_date',
        'delivery_date',
        'payment_date',
        'po_num',
        'inner_remarks',
        'remarks',
        'pay_method_id',
        'delivered_by',
        'created_by',
        'updated_by',
        'handled_by',
    ];

    protected $casts = [
        'deals_json' => 'json',
        'order_date' => 'datetime',
        'delivery_date' => 'datetime',
        'payment_date' => 'datetime',
    ];

    // mutator and accessor
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function subtotalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value/ 100,
            set: fn ($value) => $value * 100,
        );
    }

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function maintenances()
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function payMethod()
    {
        return $this->belongsTo(PayMethod::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
