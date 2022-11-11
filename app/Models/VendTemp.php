<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTemp extends Model
{
    use HasFactory;

    const TEMPERATURE_ERROR = 32767;
    const TYPE_CHAMBER = 1;
    const TYPE_EVAPORATOR = 2;

    protected $fillable = [
        'vend_id',
        'value',
        'is_keep',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // relationships
    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
