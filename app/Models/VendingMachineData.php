<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendingMachineData extends Model
{
    use HasFactory;

    protected $fillable = [
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];
}
