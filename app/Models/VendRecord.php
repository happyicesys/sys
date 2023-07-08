<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'day',
        'failure_amount',
        'failure_count',
        'month',
        'monthname',
        'operator_id',
        'total_amount',
        'total_count',
        'vend_id',
        'year',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
