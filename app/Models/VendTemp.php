<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTemp extends Model
{
    use HasFactory;

    const DEFAULT_ALERTS = [
        'TEMP_TYPE_VARIANCE_TIER_ONE' =>
        [
            'desc' => 'T1-T2 >= 5°C',
            'value' => 5,
            'is_triggered' => false,
        ],
        'TEMP_TYPE_VARIANCE_TIER_TWO' =>
        [
            'desc' => 'T1-T2 >= 10°C',
            'value' => 10,
            'is_triggered' => false,
        ],
    ];

    const TEMPERATURE_ERROR = 32767;

    const TYPE_CHAMBER = 1;
    const TYPE_EVAPORATOR = 2;
    const TYPE_THREE = 3;
    const TYPE_FOUR = 4;

    protected $fillable = [
        'vend_id',
        'value',
        'is_keep',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // relationships
    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
