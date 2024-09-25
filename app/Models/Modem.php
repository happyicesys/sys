<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
    ];

    public function modemUnits()
    {
        return $this->hasMany(ModemUnit::class);
    }
}
