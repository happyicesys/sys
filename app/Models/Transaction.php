<?php

namespace App\Models;

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
        'payment_method_id',
        'delivered_by',
        'created_by',
        'updated_by',
        'handled_by',
    ];
}
