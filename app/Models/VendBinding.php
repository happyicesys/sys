<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'begin_date',
        'termination_date',
        'is_active',
        'customer_id',
        'vend_id',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
