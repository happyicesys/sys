<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModemType extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'desc',
        'is_modem_unit_required',
        'is_resetable',
    ];

    // relationships
    public function modemTypes()
    {
        return $this->hasMany(ModemType::class);
    }

    public function modemUnits()
    {
        return $this->hasMany(ModemUnit::class);
    }
}
