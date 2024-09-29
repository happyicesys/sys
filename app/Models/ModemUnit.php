<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModemUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'imei',
        'modem_type_id',
        'is_active',
    ];

    // relationships
    public function modemType()
    {
        return $this->belongsTo(ModemType::class);
    }

    public function vend()
    {
        return $this->hasOne(Vend::class);
    }
}
