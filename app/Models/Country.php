<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency_name',
        'currency_symbol',
        'phone_code',
        'is_state',
        'sequence',
    ];
}
