<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannelRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'after_data_json',
        'after_data_created_at',
        'after_label',
        'before_data_json',
        'before_data_created_at',
        'before_label',
        'customer_id',
        'operator_id',
        'vend_id',
    ];

    protected $casts = [
        'after_data_json' => 'json',
        'before_data_json' => 'json',
        'stage_data_json' => 'json',
        'after_data_created_at' => 'datetime',
        'before_data_created_at' => 'datetime',
        'stage_data_created_at' => 'datetime',
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
