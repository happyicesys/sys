<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendData extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection',
        'ip_address',
        'processed',
        'type',
        'value',
        'vend_code',
    ];

    protected $casts = [
        'value' => 'json',
        'processed' => 'json',
    ];
}
