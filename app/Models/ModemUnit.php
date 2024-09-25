<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModemUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'imei',
        'modem_id',
        'is_active',
    ];
}
