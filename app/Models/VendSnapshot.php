<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendSnapshot extends Model
{
    use HasFactory;

    protected $casts = [
        'parameter_json' => 'json',
        'vend_channels_json' => 'json',
    ];

    protected $fillable = [
        'customer_id',
        'operator_id',
        'parameter_json',
        'vend_id',
        'vend_code',
        'vend_channels_json',
    ];

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
